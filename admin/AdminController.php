<?php
class AdminController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function listUsers() {
        $query = "SELECT id, username, email, first_name, last_name, role, school, 
                         is_active, last_login, created_at 
                  FROM users 
                  ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function deleteUser($userId) {
        try {
            $this->db->beginTransaction();
            
            // Ta bort användarens whiteboards och tillhörande data
            $whiteboards = $this->getWhiteboardsByUser($userId);
            foreach($whiteboards as $whiteboard) {
                $this->deleteWhiteboard($whiteboard['id']);
            }
            
            // Ta bort användaren
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function resetPassword($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $expires]);
        
        return $token;
    }

    public function toggleUserStatus($userId, $status) {
        // Normalisera status till 0/1
        $normalized = (int)$status === 1 ? 1 : 0;
        $stmt = $this->db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        return $stmt->execute([$normalized, $userId]);
    }

    /**
     * Set a new password for a user. Hashes the password and updates the DB.
     * Tries modern columns (password_hash + force_password_change) and falls back to legacy `password` if needed.
     */
    public function setPassword(int $userId, string $plainPassword): bool {
        if ($userId <= 0) { return false; }
        $plainPassword = trim($plainPassword);
        if ($plainPassword === '') { return false; }

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($hash === false) { return false; }

        // Försök först med modern schema
        try {
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?");
            if ($stmt->execute([$hash, $userId])) {
                return true;
            }
        } catch (\Throwable $e) {
            // Ignorera och försök legacy nedan
        }

        // Legacy: endast en `password`-kolumn
        try {
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            return $stmt->execute([$hash, $userId]);
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public function updateUser($userId, $data) {
        // Normalisera inkommande fält
        if (isset($data['is_active'])) {
            $data['is_active'] = ((string)$data['is_active'] === '1' || (int)$data['is_active'] === 1) ? 1 : 0;
        }
        if (isset($data['role'])) {
            $role = strtolower(trim($data['role']));
            $data['role'] = in_array($role, ['admin','teacher'], true) ? $role : 'teacher';
        }

        $allowedFields = ['username', 'email', 'first_name', 'last_name', 'role', 'school', 'is_active'];
        $updates = [];
        $values = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $values[] = $userId;
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }

    public function listWhiteboards() {
        $query = "SELECT w.*, u.username, u.email 
                FROM whiteboards w 
                LEFT JOIN users u ON w.user_id = u.id 
                ORDER BY w.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteWhiteboard($whiteboardId) {
        try {
            $this->db->beginTransaction();
            
            // Delete associated widgets
            $stmt = $this->db->prepare("DELETE FROM widgets WHERE whiteboard_id = ?");
            $stmt->execute([$whiteboardId]);
            
            // Delete associated student groups
            $stmt = $this->db->prepare("DELETE FROM student_groups WHERE whiteboard_id = ?");
            $stmt->execute([$whiteboardId]);
            
            // Delete the whiteboard
            $stmt = $this->db->prepare("DELETE FROM whiteboards WHERE id = ?");
            $stmt->execute([$whiteboardId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getWhiteboardsByUser($userId) {
        $stmt = $this->db->prepare("SELECT * FROM whiteboards WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUser($userId) {
        $stmt = $this->db->prepare("SELECT id, username, email, first_name, last_name, role, school, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Begränsningar för antal whiteboards per användare
    public function setGlobalWhiteboardLimit($limit) {
        // Kontrollera om system_settings-tabellen finns
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'system_settings'");
            $tableExists = $stmt->rowCount() > 0;
            
            if ($tableExists) {
                // Använd system_settings för globala inställningar
                $stmt = $this->db->prepare("SELECT id FROM system_settings WHERE setting_key = 'global_whiteboard_limit'");
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Uppdatera existerande inställning
                    $stmt = $this->db->prepare("UPDATE system_settings SET setting_value = :limit WHERE setting_key = 'global_whiteboard_limit'");
                } else {
                    // Skapa ny inställning
                    $stmt = $this->db->prepare("INSERT INTO system_settings (setting_key, setting_value, description) 
                                    VALUES ('global_whiteboard_limit', :limit, 'Det maximala antalet whiteboards som varje användare kan skapa som standard')");
                }
                
                $stmt->bindParam(':limit', $limit);
                return $stmt->execute();
            } else {
                // Använd whiteboard_limits om system_settings inte finns
                $stmt = $this->db->prepare("INSERT INTO whiteboard_limits (user_id, max_whiteboards) VALUES (NULL, ?) 
                                        ON DUPLICATE KEY UPDATE max_whiteboards = ?");
                return $stmt->execute([$limit, $limit]);
            }
        } catch (PDOException $e) {
            // Om något går fel, använd whiteboard_limits som fallback
            $stmt = $this->db->prepare("INSERT INTO whiteboard_limits (user_id, max_whiteboards) VALUES (NULL, ?) 
                                    ON DUPLICATE KEY UPDATE max_whiteboards = ?");
            return $stmt->execute([$limit, $limit]);
        }
    }

    public function setUserWhiteboardLimit($userId, $limit) {
        $stmt = $this->db->prepare("INSERT INTO whiteboard_limits (user_id, max_whiteboards) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE max_whiteboards = ?");
        return $stmt->execute([$userId, $limit, $limit]);
    }

    /**
     * Hämtar whiteboard-begränsningen för en användare eller den globala begränsningen
     * 
     * @param int|null $userId Användar-ID eller null för global begränsning
     * @return int Begränsningen som ett heltal
     */
    public function getWhiteboardLimit($userId = null) {
        // Kontrollera först om system_settings-tabellen finns
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'system_settings'");
            $systemSettingsExists = $stmt->rowCount() > 0;
            
            if ($systemSettingsExists) {
                // Använd system_settings för globala inställningar
                if ($userId !== null) {
                    // Hämta användarens specifika begränsning
                    $stmt = $this->db->prepare("SELECT max_whiteboards FROM whiteboard_limits WHERE user_id = :user_id");
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        return (int)$row['max_whiteboards'];
                    }
                }
                
                // Hämta global begränsning från system_settings
                $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'global_whiteboard_limit'");
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    return (int)$row['setting_value'];
                }
            } else {
                // Använd whiteboard_limits om system_settings inte finns
                if ($userId) {
                    $stmt = $this->db->prepare("SELECT max_whiteboards FROM whiteboard_limits 
                                            WHERE user_id = ? OR user_id IS NULL 
                                            ORDER BY user_id DESC LIMIT 1");
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $this->db->prepare("SELECT max_whiteboards FROM whiteboard_limits 
                                            WHERE user_id IS NULL LIMIT 1");
                    $stmt->execute();
                }
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    return (int)$result['max_whiteboards'];
                }
            }
        } catch (PDOException $e) {
            // Om något går fel, försök med whiteboard_limits
            if ($userId) {
                $stmt = $this->db->prepare("SELECT max_whiteboards FROM whiteboard_limits 
                                        WHERE user_id = ? OR user_id IS NULL 
                                        ORDER BY user_id DESC LIMIT 1");
                $stmt->execute([$userId]);
            } else {
                $stmt = $this->db->prepare("SELECT max_whiteboards FROM whiteboard_limits 
                                        WHERE user_id IS NULL LIMIT 1");
                $stmt->execute();
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return (int)$result['max_whiteboards'];
            }
        }
        
        // Om ingen begränsning hittades, returnera ett standardvärde
        return 5; // Standard: 5 whiteboards
    }

    public function getUserWhiteboardCount($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM whiteboards WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // Statistik för whiteboards
    public function getWhiteboardStats() {
        $stats = [];
        
        // Totalt antal whiteboards
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM whiteboards");
        $stats['total_whiteboards'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Aktiva whiteboards
        $stmt = $this->db->query("SELECT COUNT(*) as active FROM whiteboards WHERE is_active = 1");
        $stats['active_whiteboards'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
        
        // Användare nära sin gräns (>90% använt)
        $stmt = $this->db->query("
            WITH user_counts AS (
                SELECT user_id, COUNT(*) as wb_count
                FROM whiteboards
                GROUP BY user_id
            ),
            user_limits AS (
                SELECT user_id, max_whiteboards
                FROM whiteboard_limits
                WHERE user_id IS NOT NULL
                UNION
                SELECT w.user_id, wl.max_whiteboards
                FROM whiteboards w
                CROSS JOIN (
                    SELECT max_whiteboards 
                    FROM whiteboard_limits 
                    WHERE user_id IS NULL 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ) wl
                WHERE NOT EXISTS (
                    SELECT 1 
                    FROM whiteboard_limits 
                    WHERE user_id = w.user_id
                )
            )
            SELECT COUNT(DISTINCT uc.user_id) as near_limit
            FROM user_counts uc
            JOIN user_limits ul ON uc.user_id = ul.user_id
            WHERE uc.wb_count >= (ul.max_whiteboards * 0.9)
        ");
        $stats['users_near_limit'] = $stmt->fetch(PDO::FETCH_ASSOC)['near_limit'];
        
        return $stats;
    }

    /**
     * Hämtar de senaste registrerade användarna
     * 
     * @param int $limit Antal användare att hämta
     * @return array Användardata
     */
    public function getRecentUsers($limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, role, first_name, last_name, created_at 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Hämtar de senaste skapade whiteboards
     * 
     * @param int $limit Antal whiteboards att hämta
     * @return array Whiteboard-data
     */
    public function getRecentWhiteboards($limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT w.id, w.board_code, w.name, w.user_id, w.created_at,
                       u.username
                FROM whiteboards w
                LEFT JOIN users u ON w.user_id = u.id
                ORDER BY w.created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}