<?php
// auth/verify-login.php

function verifyLogin($identifier, $password) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT u.*, 
               CASE WHEN u.is_active = 0 THEN 
                    (SELECT a.expires_at > NOW() 
                     FROM account_activations a 
                     WHERE a.user_id = u.id) 
               ELSE true 
               END as is_valid 
        FROM users u 
        WHERE u.email = ? OR u.username = ?
    ");
    
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Felaktigt användarnamn eller lösenord'];
    }
    
    if (!$user['is_active']) {
        if (!$user['is_valid']) {
            return [
                'success' => false, 
                'message' => 'Ditt konto har inte aktiverats i tid. Vänligen registrera dig igen.'
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Vänligen aktivera ditt konto via länken i aktiveringsmailet.'
            ];
        }
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Felaktigt användarnamn eller lösenord'];
    }
    
    return ['success' => true, 'user' => $user];
}