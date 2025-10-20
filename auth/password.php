<?php

class PasswordUtils {
    // Konstanter för lösenordskrav
    const MIN_LENGTH = 8;
    const REQUIRE_SPECIAL_CHAR = true;
    const REQUIRE_NUMBER = true;

    /**
     * Validera lösenordskrav
     */
    public static function validatePassword(string $password): array {
        $errors = [];
        
        // Kontrollera längd
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Lösenordet måste vara minst " . self::MIN_LENGTH . " tecken långt";
        }

        // Kontrollera specialtecken och siffror
        if (self::REQUIRE_SPECIAL_CHAR && !preg_match('/[\W]/', $password)) {
            if (self::REQUIRE_NUMBER && !preg_match('/\d/', $password)) {
                $errors[] = "Lösenordet måste innehålla minst en siffra eller ett specialtecken";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Skapa säkert lösenordshash
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }

    /**
     * Verifiera lösenord
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Generera säker token för aktivering/återställning
     */
    public static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }
}