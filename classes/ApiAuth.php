<?php
/**
 * API Authentication and Validation Class
 */
class ApiAuth {
    private $validApiKeys;
    private $db;

    public function __construct($database = null) {
        // Load environment variables
        require_once __DIR__ . '/EnvLoader.php';
        EnvLoader::load();
        
        $this->db = $database;
        
        // Get API keys from environment variables as fallback
        $this->validApiKeys = [];
        
        // Support multiple API keys from environment
        if (!empty($_ENV['API_KEY_1'])) {
            $this->validApiKeys[] = $_ENV['API_KEY_1'];
        }
        if (!empty($_ENV['API_KEY_2'])) {
            $this->validApiKeys[] = $_ENV['API_KEY_2'];
        }
        
        // Fallback to default keys if no environment keys found
        if (empty($this->validApiKeys)) {
            $this->validApiKeys = [
                'your-api-key-here',
                'another-valid-key'
            ];
        }
    }

    public function validateApiKey($apiKey) {
        // First try database validation if database connection is available
        if ($this->db !== null) {
            require_once __DIR__ . '/ApiKeyManager.php';
            $apiKeyManager = new ApiKeyManager($this->db);
            return $apiKeyManager->isValidKey($apiKey);
        }
        
        // Fallback to environment/hardcoded keys
        return in_array($apiKey, $this->validApiKeys);
    }

    public function validateHostname($hostname) {
        // Pattern: 4char-2char4num-CCAPxxx
        // Example: GV-RC0011-CCAP003
        $pattern = '/^[a-zA-Z]{2}-[a-zA-Z]{2}[0-9]{4}-CCAP[0-9a-zA-Z*%]*$/i';
        
        // Check for wildcard patterns
        if (strpos($hostname, 'CCAP*') !== false || strpos($hostname, 'CCAP%') !== false) {
            return true; // Wildcard query
        }
        
        return preg_match($pattern, $hostname);
    }

    public function validateIpAddress($ip) {
        // Allow wildcards in IP addresses
        if (strpos($ip, '*') !== false || strpos($ip, '%') !== false) {
            // Basic validation for IP with wildcards
            $ipPattern = '/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|\*|%)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|\*|%)$/';
            return preg_match($ipPattern, $ip);
        }
        
        // Standard IP validation
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public function prepareHostnameQuery($hostname) {
        // Convert search patterns to SQL LIKE patterns
        $hostname = str_replace('*', '%', $hostname);
        return $hostname;
    }

    public function prepareIpQuery($ip) {
        // Convert search patterns to SQL LIKE patterns
        $ip = str_replace('*', '%', $ip);
        return $ip;
    }
}
