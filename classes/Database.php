<?php
/**
 * Database connection class
 */
class Database {
    private $connection;
    private $config;

    public function __construct() {
        $this->config = require_once __DIR__ . '/../config/database.php';
        $this->connect();
    }

    private function connect() {
        try {
            // Connect without specifying a database to allow cross-database queries
            $dsn = "mysql:host={$this->config['host']};charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    /**
     * Execute a SQL query with params and return results
     * 
     * @param string $sql The SQL query to execute
     * @param array $params The parameters to bind to the query
     * @return array The query results
     * @throws Exception If the query fails
     */
    public function query($sql, $params = []) {
        try {
            // Format the query for debugging by replacing ? with actual values
            $loggedSql = preg_replace('/\s+/', ' ', trim($sql));
            
            // Create a debug version with the actual parameters
            $debugSql = $sql;
            if (!empty($params)) {
                foreach ($params as $param) {
                    // Replace the first ? with the value
                    $pos = strpos($debugSql, '?');
                    if ($pos !== false) {
                        $value = is_string($param) ? "'" . addslashes($param) . "'" : $param;
                        $debugSql = substr_replace($debugSql, $value, $pos, 1);
                    }
                }
            }
            
            // Log both the parameterized query and the query with values
            error_log("Executing SQL: " . $loggedSql);
            error_log("SQL with params: " . preg_replace('/\s+/', ' ', trim($debugSql)));
            
            $stmt = $this->connection->prepare($sql);
            
            // Execute and check for errors
            if (!$stmt->execute($params)) {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error: " . json_encode($errorInfo));
                throw new Exception("Query execution failed: " . $errorInfo[2]);
            }
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Query returned " . count($result) . " rows");
            return $result;
        } catch (PDOException $e) {
            $debugSql = $debugSql ?? $loggedSql;
            error_log("Database error: " . $e->getMessage());
            error_log("Failed SQL: " . preg_replace('/\s+/', ' ', trim($debugSql)));
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
}
