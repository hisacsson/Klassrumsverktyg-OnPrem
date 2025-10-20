<?php
// src/Security/MFA.php
namespace App\Security;

use PDO;

class MFA {
    // === TOTP ===
    public static function base32_encode(string $bin): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = ''; for ($i=0; $i<strlen($bits); $i+=5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    public static function randomSecret(int $bytes = 20): string {
        return random_bytes($bytes); // lagras som binärt i VARBINARY
    }

    public static function provisioningUri(string $issuer, string $label, string $base32Secret): string {
        $issuerEnc = rawurlencode($issuer);
        $labelEnc  = rawurlencode($label);
        return "otpauth://totp/{$issuerEnc}:{$labelEnc}?secret={$base32Secret}&issuer={$issuerEnc}&algorithm=SHA1&digits=6&period=30";
    }

    public static function hotp(string $secretBin, int $counter): string {
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $secretBin, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (ord($hash[$offset]) & 0x7F) << 24 |
                     (ord($hash[$offset+1]) & 0xFF) << 16 |
                     (ord($hash[$offset+2]) & 0xFF) << 8  |
                     (ord($hash[$offset+3]) & 0xFF);
        $code = $truncated % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    public static function totpNow(string $secretBin, int $time = null): string {
        $t = ($time ?? time()) / 30;
        return self::hotp($secretBin, (int) floor($t));
    }

    public static function verifyTotp(string $secretBin, string $code, int $window = 1): bool {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $ts = time();
        for ($i=-$window; $i<=$window; $i++) {
            if (hash_equals(self::totpNow($secretBin, $ts + ($i*30)), $code)) return true;
        }
        return false;
    }

    // === Recovery codes ===
    public static function generateRecoveryCodes(int $count = 10): array {
        $codes = [];
        for ($i=0; $i<$count; $i++) {
            // 10 tecken A-Z0-9, lätt att skriva
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }
        return $codes;
    }

    public static function hashToken(string $token): string {
        return hash('sha256', $token, true); // binär 32 bytes → passar VARBINARY(64)
    }

    // === Persistens ===
    public static function startEnrollment(PDO $pdo, int $userId): array {
        $secret = self::randomSecret(20);
        // Lägg temporärt i session i stället för DB tills bekräftad
        $_SESSION['mfa_tmp_secret_'.$userId] = $secret;
        return ['secret_bin' => $secret];
    }

    public static function confirmEnable(PDO $pdo, int $userId, string $code, string $email, string $siteName): array {
        $key = 'mfa_tmp_secret_'.$userId;
        if (empty($_SESSION[$key])) return ['ok'=>false, 'msg'=>'Ingen pågående aktivering'];
        $secret = $_SESSION[$key];
        if (!self::verifyTotp($secret, $code)) return ['ok'=>false, 'msg'=>'Fel kod'];

        // Spara i users
        $stmt = $pdo->prepare("UPDATE users
            SET mfa_enabled=1, mfa_secret=?, mfa_enrolled_at=NOW()
            WHERE id=?");
        $stmt->execute([$secret, $userId]);

        unset($_SESSION[$key]);

        // Skapa recovery codes (returnera i klartext EN gång)
        $codes = self::generateRecoveryCodes(10);
        $ins = $pdo->prepare("INSERT INTO user_mfa_recovery_codes (user_id, code_hash) VALUES (?, ?)");
        foreach ($codes as $c) {
            $ins->execute([$userId, self::hashToken($c)]);
        }

        // Returnera data för UI
        $base32 = self::base32_encode($secret);
        $uri = self::provisioningUri($siteName, $email, $base32);
        return ['ok'=>true, 'codes'=>$codes, 'base32'=>$base32, 'otpauth_uri'=>$uri];
    }

    public static function disable(PDO $pdo, int $userId): void {
        $pdo->prepare("UPDATE users SET mfa_enabled=0, mfa_secret=NULL, mfa_enrolled_at=NULL, mfa_last_verified_at=NULL WHERE id=?")
            ->execute([$userId]);
        $pdo->prepare("DELETE FROM user_mfa_devices WHERE user_id=?")->execute([$userId]);
        $pdo->prepare("DELETE FROM user_mfa_recovery_codes WHERE user_id=?")->execute([$userId]);
    }

    public static function verifyTotpOrRecovery(PDO $pdo, int $userId, string $code): bool {
        $code = strtoupper(trim($code));
        // 1) Testa TOTP
        $row = $pdo->prepare("SELECT mfa_secret FROM users WHERE id=? AND mfa_enabled=1");
        $row->execute([$userId]);
        $secret = $row->fetchColumn();
        if ($secret && self::verifyTotp($secret, $code)) {
            $pdo->prepare("UPDATE users SET mfa_last_verified_at=NOW() WHERE id=?")->execute([$userId]);
            return true;
        }
        // 2) Testa recovery
        $sel = $pdo->prepare("SELECT id FROM user_mfa_recovery_codes WHERE user_id=? AND used_at IS NULL AND code_hash=? LIMIT 1");
        $sel->execute([$userId, self::hashToken($code)]);
        $rcId = $sel->fetchColumn();
        if ($rcId) {
            $pdo->prepare("UPDATE user_mfa_recovery_codes SET used_at=NOW() WHERE id=?")->execute([$rcId]);
            $pdo->prepare("UPDATE users SET mfa_last_verified_at=NOW() WHERE id=?")->execute([$userId]);
            return true;
        }
        return false;
    }

    // === Remember device ===
    public static function fingerprint(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'ua';
        $h = substr(hash('sha256', $ua, true), 0, 32);
        return $h;
    }

    public static function rememberSet(PDO $pdo, int $userId, int $days = 30): array {
        $token = bin2hex(random_bytes(32)); // skickas i cookie (klartext)
        $hash  = self::hashToken($token);   // lagras i DB
        $fp    = self::fingerprint();
        $pdo->prepare("INSERT INTO user_mfa_devices (user_id, device_fingerprint_hash, token_hash, expires_at)
                       VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))")
            ->execute([$userId, $fp, $hash, $days]);
        return ['token'=>$token];
    }

    public static function rememberCheck(PDO $pdo, int $userId, ?string $tokenFromCookie): bool {
        if (!$tokenFromCookie) return false;
        $hash = self::hashToken($tokenFromCookie);
        $fp   = self::fingerprint();
        $sel = $pdo->prepare("SELECT id FROM user_mfa_devices
                              WHERE user_id=? AND token_hash=? AND device_fingerprint_hash=? AND (expires_at IS NULL OR expires_at>NOW())
                              LIMIT 1");
        $sel->execute([$userId, $hash, $fp]);
        $id = $sel->fetchColumn();
        if ($id) {
            $pdo->prepare("UPDATE user_mfa_devices SET last_used_at=NOW() WHERE id=?")->execute([$id]);
            return true;
        }
        return false;
    }

    public static function rememberClearAll(PDO $pdo, int $userId): void {
        $pdo->prepare("DELETE FROM user_mfa_devices WHERE user_id=?")->execute([$userId]);
    }
}