<?php
/**
 * Quick verification of existing reporting.acc_alias table
 */

require_once 'classes/Database.php';

echo "=== Verifying Existing reporting.acc_alias Table ===\n";

try {
    $db = new Database();
    
    // Check table structure
    echo "1. Table structure:\n";
    $structure = $db->query("DESCRIBE reporting.acc_alias");
    foreach ($structure as $column) {
        echo "   {$column['Field']}: {$column['Type']}" . 
             ($column['Null'] === 'NO' ? ' NOT NULL' : '') . 
             ($column['Key'] ? " ({$column['Key']})" : '') . "\n";
    }
    
    // Check sample data
    echo "\n2. Sample data (first 10 rows):\n";
    $data = $db->query("SELECT * FROM reporting.acc_alias LIMIT 10");
    
    if (empty($data)) {
        echo "   No data found in table\n";
    } else {
        // Show headers
        $headers = array_keys($data[0]);
        echo "   " . implode(" | ", $headers) . "\n";
        echo "   " . str_repeat("-", strlen(implode(" | ", $headers))) . "\n";
        
        // Show data
        foreach ($data as $row) {
            echo "   " . implode(" | ", $row) . "\n";
        }
    }
    
    // Check total count
    $count = $db->query("SELECT COUNT(*) as total FROM reporting.acc_alias");
    echo "\n3. Total records: " . $count[0]['total'] . "\n";
    
    echo "\n✅ Table exists and is accessible\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
