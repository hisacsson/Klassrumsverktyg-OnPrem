<?php
class WhiteboardController {
   private $db;

   public function __construct($db) {
       $this->db = $db;
   }

   public function getWhiteboardsForUser($userId) {
       $stmt = $this->db->prepare("
           SELECT id, name, board_code, created_at, updated_at,
                  CASE WHEN password IS NOT NULL THEN 1 ELSE 0 END as is_password_protected
           FROM whiteboards 
           WHERE user_id = ? AND is_active = 1
           ORDER BY updated_at DESC
       ");
       $stmt->execute([$userId]);
       return $stmt->fetchAll(PDO::FETCH_ASSOC);
   }

   public function createWhiteboard($userId, $name) {
       if (!$this->canCreateWhiteboard($userId)) {
           throw new Exception('Du har nått maxgränsen för antal whiteboards');
       }

       $boardCode = $this->generateBoardCode();
       
       $stmt = $this->db->prepare("
           INSERT INTO whiteboards (name, board_code, user_id) 
           VALUES (?, ?, ?)
       ");
       
       return $stmt->execute([$name, $boardCode, $userId]);
   }

   public function updateWhiteboard($userId, $boardId, $name) {
       $stmt = $this->db->prepare("
           UPDATE whiteboards 
           SET name = ?, updated_at = CURRENT_TIMESTAMP 
           WHERE id = ? AND user_id = ? AND is_active = 1
       ");
       
       return $stmt->execute([$name, $boardId, $userId]);
   }

   public function updateWhiteboardPassword($userId, $boardId, $password) {
       $stmt = $this->db->prepare("
           UPDATE whiteboards 
           SET password = ?, updated_at = CURRENT_TIMESTAMP
           WHERE id = ? AND user_id = ? AND is_active = 1
       ");
       
       $hashedPassword = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
       return $stmt->execute([$hashedPassword, $boardId, $userId]);
   }

   public function verifyWhiteboardPassword($boardCode, $password) {
       $stmt = $this->db->prepare("
           SELECT password 
           FROM whiteboards 
           WHERE board_code = ? AND is_active = 1
       ");
       $stmt->execute([$boardCode]);
       $board = $stmt->fetch(PDO::FETCH_ASSOC);
       
       return $board && password_verify($password, $board['password']);
   }

   public function deleteWhiteboard($userId, $boardId) {
       try {
           $this->db->beginTransaction();

           $stmt = $this->db->prepare("SELECT id FROM whiteboards WHERE id = ? AND user_id = ?");
           $stmt->execute([$boardId, $userId]);
           if (!$stmt->fetch()) {
               throw new Exception("Unauthorized access");
           }

           $stmt = $this->db->prepare("DELETE FROM student_groups WHERE whiteboard_id = ?");
           $stmt->execute([$boardId]);

           $stmt = $this->db->prepare("DELETE FROM widgets WHERE whiteboard_id = ?");
           $stmt->execute([$boardId]);

           $stmt = $this->db->prepare("DELETE FROM whiteboards WHERE id = ?");
           $stmt->execute([$boardId]);

           $this->db->commit();
           return true;

       } catch (Exception $e) {
           $this->db->rollBack();
           error_log("Error deleting whiteboard: " . $e->getMessage());
           return false;
       }
   }

   private function canCreateWhiteboard($userId) {
       $currentCount = $this->getUserWhiteboardCount($userId);
       $limit = $this->getWhiteboardLimit($userId);
       
       if (!$limit) {
           return true;
       }
       
       return $currentCount < $limit;
   }

   private function getUserWhiteboardCount($userId) {
       $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM whiteboards WHERE user_id = ? AND is_active = 1");
       $stmt->execute([$userId]);
       $result = $stmt->fetch(PDO::FETCH_ASSOC);
       return $result['count'];
   }

   private function getWhiteboardLimit($userId) {
       $stmt = $this->db->prepare("
           SELECT max_whiteboards 
           FROM whiteboard_limits 
           WHERE user_id = ?
       ");
       $stmt->execute([$userId]);
       $userLimit = $stmt->fetch(PDO::FETCH_ASSOC);

       if ($userLimit) {
           return $userLimit['max_whiteboards'];
       }

       $stmt = $this->db->prepare("
           SELECT max_whiteboards 
           FROM whiteboard_limits 
           WHERE user_id IS NULL 
           ORDER BY created_at DESC 
           LIMIT 1
       ");
       $stmt->execute();
       $globalLimit = $stmt->fetch(PDO::FETCH_ASSOC);

       return $globalLimit ? $globalLimit['max_whiteboards'] : null;
   }

   private function generateBoardCode() {
       $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
       $code = '';
       
       do {
           $code = '';
           for ($i = 0; $i < 6; $i++) {
               $code .= $characters[rand(0, strlen($characters) - 1)];
           }
           
           $stmt = $this->db->prepare("SELECT COUNT(*) FROM whiteboards WHERE board_code = ?");
           $stmt->execute([$code]);
       } while ($stmt->fetchColumn() > 0);
       
       return $code;
   }
}