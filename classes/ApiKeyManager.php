<?php
/**
 * API Key Management Class
 */
class ApiKeyManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getAllKeys() {
        $sql = "SELECT ak.*, au.username as created_by_name 
                FROM api_keys ak 
                LEFT JOIN admin_users au ON ak.created_by = au.id 
                ORDER BY ak.created_at DESC";
        return $this->db->query($sql);
    }
    
    public function createKey($keyName, $description, $createdBy, $expiresAt = null) {
        $apiKey = $this->generateApiKey();
        
        $sql = "INSERT INTO api_keys (key_name, api_key, description, created_by, expires_at) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [$keyName, $apiKey, $description, $createdBy, $expiresAt]);
        
        return $apiKey;
    }
    
    public function toggleKeyStatus($keyId) {
        $sql = "UPDATE api_keys SET is_active = NOT is_active WHERE id = ?";
        $this->db->query($sql, [$keyId]);
    }
    
    public function deleteKey($keyId) {
        $sql = "DELETE FROM api_keys WHERE id = ?";
        $this->db->query($sql, [$keyId]);
    }
    
    public function isValidKey($apiKey) {
        $sql = "SELECT id, usage_count FROM api_keys 
                WHERE api_key = ? AND is_active = 1 
                AND (expires_at IS NULL OR expires_at > NOW())";
        
        $result = $this->db->query($sql, [$apiKey]);
        
        if (!empty($result)) {
            // Update usage count and last used
            $keyId = $result[0]['id'];
            $usageCount = $result[0]['usage_count'] + 1;
            
            $updateSql = "UPDATE api_keys SET usage_count = ?, last_used = NOW() WHERE id = ?";
            $this->db->query($updateSql, [$usageCount, $keyId]);
            
            return true;
        }
        
        return false;
    }
    
    private function generateApiKey() {
        return 'ak_' . bin2hex(random_bytes(16));
    }
    
    public function getKeyStats() {
        $stats = [];
        
        // Total keys
        $sql = "SELECT COUNT(*) as total FROM api_keys";
        $result = $this->db->query($sql);
        $stats['total'] = $result[0]['total'];
        
        // Active keys
        $sql = "SELECT COUNT(*) as active FROM api_keys WHERE is_active = 1";
        $result = $this->db->query($sql);
        $stats['active'] = $result[0]['active'];
        
        // Total usage
        $sql = "SELECT SUM(usage_count) as total_usage FROM api_keys";
        $result = $this->db->query($sql);
        $stats['total_usage'] = $result[0]['total_usage'] ?? 0;
        
        return $stats;
    }
}
