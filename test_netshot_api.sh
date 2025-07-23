#!/bin/bash

# Test script for Netshot API integration with ISW CMDB API
# This script focuses specifically on testing the IP address lookups which use Netshot

# Configuration
BASE_URL="${BASE_URL:-http://localhost/isw}"
AUTH_USER="isw"
AUTH_PASS="Spyem_OtGheb4"
NETSHOT_URL="${NETSHOT_URL:-https://netshot.oss.local/api}"
CLEAR_CACHE=0

# Process command line arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    --base-url=*)
      BASE_URL="${1#*=}"
      shift
      ;;
    --auth-user=*)
      AUTH_USER="${1#*=}"
      shift
      ;;
    --auth-pass=*)
      AUTH_PASS="${1#*=}"
      shift
      ;;
    --netshot-url=*)
      NETSHOT_URL="${1#*=}"
      shift
      ;;
    --clear-cache)
      CLEAR_CACHE=1
      shift
      ;;
    *)
      echo "Unknown option: $1"
      echo "Usage: $0 [--base-url=URL] [--auth-user=USER] [--auth-pass=PASS] [--netshot-url=URL] [--clear-cache]"
      exit 1
      ;;
  esac
done

echo "=== ISW CMDB API - Netshot Integration Test ==="
echo "Base URL: $BASE_URL"
echo "Authentication: $AUTH_USER:$AUTH_PASS"
echo "Netshot URL: $NETSHOT_URL"

# Function to make an authenticated API call
api_call() {
    local endpoint=$1
    local query=$2
    echo -e "\n> Calling: $BASE_URL$endpoint$query"
    curl -s -u "$AUTH_USER:$AUTH_PASS" "$BASE_URL$endpoint$query"
}

# Test 1: IP exact search (should use Netshot API)
echo -e "\n1. Testing IP exact search via Netshot..."
IP_TO_TEST="172.16.52.2"  # Try a more likely IP based on generateIpFromHostname logic
api_call "/api/search" "?type=ip&q=$IP_TO_TEST" | jq '.'

# Test 2: IP wildcard search via Netshot
echo -e "\n2. Testing IP wildcard search via Netshot..."
IP_PATTERN="172.16.*.*"  # Pattern based on hostname-to-IP mapping logic
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
HOSTNAME_TO_TEST="GV-RC0052-CCAP002"  # Use hostname from error log that should exist in Netshot
api_call "/api/search" "?type=hostname&q=$HOSTNAME_TO_TEST" | jq '.'

# Test 5: Directly test Netshot API connection with the token
echo -e "\n5. Testing direct Netshot API connection..."
echo "Connecting directly to: $NETSHOT_URL"

# Clear cache if requested
if [ $CLEAR_CACHE -eq 1 ]; then
  echo -e "\nClearing Netshot API cache..."
  if [ -d "cache/netshot" ]; then
    rm -rf cache/netshot/*
    echo "Cache cleared!"
  else
    echo "Cache directory not found. No cache to clear."
  fi
fi

# Check if jq is installed
if command -v jq >/dev/null 2>&1; then
  # Use the NETSHOT_URL and export it for the PHP test script
  export NETSHOT_URL
  # Export clear cache flag
  export CLEAR_CACHE
  # Run the PHP test script if it exists
  if [ -f "test_netshot_connection.php" ]; then
    echo "Running Netshot connection test script..."
    php test_netshot_connection.php
  else
    echo "Netshot test script not found. Skipping direct API test."
  fi
else
  echo "jq is not installed. Skipping JSON formatting for direct API test."
fi

echo -e "\n=== Netshot Integration Test Completed ==="
