<?php
/**
 * Admin Authentication Class
 */
class AdminAuth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($username, $password) {
        $sql = "SELECT id, username, password_hash, email, is_active FROM admin_users WHERE username = ? AND is_active = 1";
        $result = $this->db->query($sql, [$username]);
        
        if (empty($result)) {
            return false;
        }
        
        $user = $result[0];
        
        if (password_verify($password, $user['password_hash'])) {
            // Update last login
            $updateSql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
            $this->db->query($updateSql, [$user['id']]);
            
            // Start session
            session_start();
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        session_start();
        session_destroy();
    }
    
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['admin_user']);
    }
    
    public function getCurrentUser() {
        session_start();
        return $_SESSION['admin_user'] ?? null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /isw/admin/login.php');
            exit();
        }
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
