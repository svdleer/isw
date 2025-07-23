<?php

require_once __DIR__ . '/../classes/NetshotAPI.php';
require_once __DIR__ . '/../classes/EnvLoader.php';

class NetshotAPITest extends PHPUnit\Framework\TestCase {
    private $netshotApi;
    
    protected function setUp(): void {
        // Load environment variables
        (new EnvLoader())->load();
        
        // Create a new NetshotAPI instance
        $this->netshotApi = new NetshotAPI();
    }
    
    public function testGetDeviceByIP() {
        // Test with a valid IP address that should exist in the system
        $result = $this->netshotApi->getDeviceByIP('10.0.0.1');
        
        // Assert that we got a result
        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('ip', $result);
    }
    
    public function testGetDeviceByIPNotFound() {
        // Test with an IP that should not exist
        $result = $this->netshotApi->getDeviceByIP('192.168.255.255');
        
        // Assert that we got null or an empty result
        $this->assertNull($result);
    }
    
    public function testGetDevicesByGroup() {
        // Test getting devices from a known group
        $results = $this->netshotApi->getDevicesByGroup('Firewalls');
        
        // Assert that we got results
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        // Check structure of first device
        $firstDevice = $results[0];
        $this->assertArrayHasKey('id', $firstDevice);
        $this->assertArrayHasKey('name', $firstDevice);
        $this->assertArrayHasKey('ip', $firstDevice);
    }
    
    public function testErrorHandling() {
        // Test with invalid API key by temporarily modifying the property
        $reflection = new ReflectionClass($this->netshotApi);
        $property = $reflection->getProperty('apiKey');
        $property->setAccessible(true);
        $originalValue = $property->getValue($this->netshotApi);
        $property->setValue($this->netshotApi, 'invalid_key');
        
        // This should return null or throw an exception that we catch in the API class
        $result = $this->netshotApi->getDeviceByIP('10.0.0.1');
        $this->assertNull($result);
        
        // Restore original value
        $property->setValue($this->netshotApi, $originalValue);
    }
}
