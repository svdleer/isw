#!/bin/bash

# Test script for Netshot API integration with ISW CMDB API
# This script focuses specifically on testing the IP address lookups which use Netshot

# Configuration
BASE_URL="${BASE_URL:-http://localhost/isw}"
AUTH_USER="isw"
AUTH_PASS="Spyem_OtGheb4"

echo "=== ISW CMDB API - Netshot Integration Test ==="
echo "Base URL: $BASE_URL"

# Function to make an authenticated API call
api_call() {
    local endpoint=$1
    local query=$2
    curl -s -u "$AUTH_USER:$AUTH_PASS" "$BASE_URL$endpoint$query"
}

# Test 1: IP exact search (should use Netshot API)
echo -e "\n1. Testing IP exact search via Netshot..."
IP_TO_TEST="192.168.1.100"  # Replace with a real IP in your Netshot system
api_call "/api/search" "?type=ip&q=$IP_TO_TEST" | jq '.'

# Test 2: IP wildcard search via Netshot
echo -e "\n2. Testing IP wildcard search via Netshot..."
IP_PATTERN="192.168.1.*"  # Replace with a suitable IP pattern for your environment
api_call "/api/search" "?type=ip&q=$IP_PATTERN" | jq '.'

# Test 3: JSON body request for IP search
echo -e "\n3. Testing JSON body request for IP search via Netshot..."
JSON_DATA='{
    "Header": {
        "BusinessTransactionID": "test-123",
        "SentTimestamp": "'$(date -Iseconds)'",
        "SourceContext": {
            "host": "test-script",
            "application": "netshot-test"
        }
    },
    "Body": {
        "IPAddress": "'$IP_TO_TEST'"
    }
}'

curl -s -u "$AUTH_USER:$AUTH_PASS" \
     -X POST \
     -H "Content-Type: application/json" \
     -d "$JSON_DATA" \
     "$BASE_URL/api/search" | jq '.'

# Test 4: Test with a hostname search to compare with IP search
echo -e "\n4. Testing hostname search for comparison..."
HOSTNAME_TO_TEST="GV-RC0011-CCAP003"  # Replace with a real hostname in your database
api_call "/api/search" "?type=hostname&q=$HOSTNAME_TO_TEST" | jq '.'

echo -e "\n=== Netshot Integration Test Completed ==="
