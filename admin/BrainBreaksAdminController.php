<?php
require_once __DIR__ . '/../src/Config/Database.php';

class BrainBreaksAdminController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get all brain breaks with optional filtering
     */
    public function getAllBrainBreaks($category = null, $userId = null, $isPublic = null) {
        $sql = "SELECT bb.*, u.username, u.first_name, u.last_name 
                FROM brain_breaks bb
                LEFT JOIN users u ON bb.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if ($category) {
            $sql .= " AND bb.category = :category";
            $params[':category'] = $category;
        }
        
        if ($userId) {
            $sql .= " AND bb.user_id = :userId";
            $params[':userId'] = $userId;
        }
        
        if ($isPublic !== null) {
            $sql .= " AND bb.is_public = :isPublic";
            $params[':isPublic'] = $isPublic ? 1 : 0;
        }
        
        $sql .= " ORDER BY bb.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all distinct categories
     */
    public function getAllCategories() {
        $sql = "SELECT DISTINCT category FROM brain_breaks WHERE category IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all users who have created brain breaks
     */
    public function getUsersWithBrainBreaks() {
        $sql = "SELECT DISTINCT u.id, u.username, u.first_name, u.last_name 
                FROM users u
                INNER JOIN brain_breaks bb ON u.id = bb.user_id
                ORDER BY u.last_name, u.first_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a brain break by ID
     */
    public function deleteBrainBreak($id) {
        $sql = "DELETE FROM brain_breaks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Add a new brain break
     */
    public function addBrainBreak($data, $userId) {
        // If a new category is provided, use that instead of the selected one
        if (isset($data['new_category']) && !empty($data['new_category'])) {
            $data['category'] = $data['new_category'];
        }

        $params = [
            ':title' => $data['title'],
            ':category' => $data['category'],
            ':duration' => $data['duration'] ?? null,
            ':youtube_id' => $data['youtube_id'] ?? null,
            ':text_content' => $data['text_content'] ?? null,
            ':is_public' => isset($data['is_public']) && $data['is_public'] ? 1 : 0,
            ':user_id' => $userId
        ];

        $sql = "INSERT INTO brain_breaks (title, category, duration, youtube_id, text_content, is_public, user_id) 
                VALUES (:title, :category, :duration, :youtube_id, :text_content, :is_public, :user_id)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $this->db->lastInsertId();
    }

    /**
     * Get a brain break by ID
     */
    public function getBrainBreakById($id) {
        $sql = "SELECT * FROM brain_breaks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>