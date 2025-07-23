<?php
/**
 * API Authentication and Validation Class
 */
class ApiAuth {
    private $db;

    public function __construct($database = null) {
        // Load environment variables
        require_once __DIR__ . '/EnvLoader.php';
        EnvLoader::load();
        
        $this->db = $database;
    }

    /**
     * Validate HTTP Basic Authentication credentials
     * 
     * @param string $username Username from Basic Auth
     * @param string $password Password from Basic Auth
     * @return bool True if credentials are valid, false otherwise
     */
    public function validateBasicAuth($username, $password) {
        // Hardcoded credentials for user 'isw'
        if ($username === 'isw' && $password === 'Spyem_OtGheb4') {
            return true;
        }
        
        // Check if we have a database connection
        if ($this->db === null) {
            // Fallback to environment credentials if no database connection
            $envUsername = $_ENV['API_USERNAME'] ?? 'admin';
            $envPassword = $_ENV['API_PASSWORD'] ?? 'password';
            
            return ($username === $envUsername && $password === $envPassword);
        }
        
        // Use the database to validate credentials
        $sql = "SELECT id, username, password_hash, is_active FROM admin_users WHERE username = ? AND is_active = 1";
        $result = $this->db->query($sql, [$username]);
        
        if (empty($result)) {
            return false;
        }
        
        $user = $result[0];
        
        return password_verify($password, $user['password_hash']);
    }
    
    /**
     * Get HTTP Basic Authentication credentials from headers
     * 
     * @return array|null Array with 'username' and 'password' or null if no auth header
     */
    public function getBasicAuthCredentials() {
        // Check for Authorization header (Apache might modify the header name)
        $authHeader = null;
        
        // Check multiple possible header locations
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            // Direct access to PHP_AUTH_* variables (when PHP is running as CGI/FastCGI)
            return [
                'username' => $_SERVER['PHP_AUTH_USER'],
                'password' => $_SERVER['PHP_AUTH_PW']
            ];
        } elseif (function_exists('apache_request_headers')) {
            // Try apache_request_headers() if available
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            }
        }
        
        // Debug: Log if auth header is missing
        if (!$authHeader) {
            error_log('No Authorization header found. Available headers: ' . json_encode($_SERVER));
            return null;
        }
        
        // Check if it's a Basic Auth header
        if (strpos($authHeader, 'Basic') !== 0) {
            error_log('Authorization header does not start with Basic: ' . $authHeader);
            return null;
        }
        
        // Get credentials
        $credentials = base64_decode(substr($authHeader, 6));
        
        if (strpos($credentials, ':') === false) {
            error_log('Invalid credentials format in Basic Auth header');
            return null;
        }
        
        list($username, $password) = explode(':', $credentials, 2);
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }

    public function validateHostname($hostname) {
        // Single wildcard case - allowed and will return all CCAP devices
        if ($hostname === '*' || $hostname === '%') {
            return true;
        }
        
        // For wildcards, ensure proper handling for both * and %
        if (strpos($hostname, '*') !== false || strpos($hostname, '%') !== false) {
            // Convert * to % for consistency in validation
            $normalizedHostname = str_replace('*', '%', $hostname);
            
            // Allow wildcards with CCAP
            if (stripos($normalizedHostname, 'CCAP') !== false) {
                return true;
            }
            
            // Allow wildcards that will have CCAP added later
            return true;
        }
        
        // Traditional pattern check for exact matches
        // Pattern: 4char-2char4num-CCAPxxx
        // Example: GV-RC0011-CCAP003
        $pattern = '/^[a-zA-Z]{2}-[a-zA-Z]{2}[0-9]{4}-CCAP[0-9a-zA-Z]*$/i';
        
        // For non-wildcard searches, strictly require CCAP in the hostname
        return preg_match($pattern, $hostname) || stripos($hostname, 'CCAP') !== false;
    }

    public function validateIpAddress($ip) {
        // Allow wildcards in IP addresses
        if (strpos($ip, '*') !== false || strpos($ip, '%') !== false) {
            // Split IP by dots
            $parts = explode('.', $ip);
            
            // Check if we have exactly 4 parts
            if (count($parts) !== 4) {
                return false;
            }
            
            // Validate each octet in the IP address
            foreach ($parts as $part) {
                if ($part === '*' || $part === '%') {
                    continue; // Wildcard is allowed
                }
                
                // If it's not a wildcard, it must be a valid octet (0-255)
                if (!is_numeric($part) || $part < 0 || $part > 255 || (strlen($part) > 1 && $part[0] === '0')) {
                    return false;
                }
            }
            
            return true;
        }
        
        // Standard IP validation for non-wildcard IPs
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public function prepareHostnameQuery($hostname) {
        // Handle pure wildcard searches - will be converted to CCAP search in main code
        if ($hostname === '*' || $hostname === '%') {
            return '%';
        }
        
        // Convert search patterns to SQL LIKE patterns
        $hostname = str_replace('*', '%', $hostname);
        
        // Ensure CCAP is part of the query if not already present
        if (stripos($hostname, 'CCAP') === false) {
            $hostname = '%CCAP%' . $hostname;
        }
        
        // If no wildcards are present, add them for partial matching
        if (strpos($hostname, '%') === false) {
            $hostname = '%' . $hostname . '%';
        }
        
        return $hostname;
    }

    public function prepareIpQuery($ip) {
        // Convert search patterns to SQL LIKE patterns
        $ip = str_replace('*', '%', $ip);
        return $ip;
    }
}
