<?php
// Simple script to create the required cache directories
// Run this once during installation

// Create cache directory structure
$cacheDir = __DIR__ . '/cache';
$netshotCacheDir = $cacheDir . '/netshot';

if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
    echo "Created main cache directory: $cacheDir\n";
}

if (!file_exists($netshotCacheDir)) {
    mkdir($netshotCacheDir, 0755, true);
    echo "Created Netshot cache directory: $netshotCacheDir\n";
}

echo "Cache directories created successfully.\n";
