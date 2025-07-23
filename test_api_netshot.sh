#!/bin/bash
# Test script for API with Netshot integration

# Define colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Define base URL - change as needed
BASE_URL="http://localhost/isw"
AUTH="isw:Spyem_OtGheb4"

echo -e "${BLUE}CMDB API Test Script with Netshot Integration${NC}"
echo -e "${BLUE}============================================${NC}\n"

# Function to make API calls
function call_api() {
    local endpoint=$1
    local params=$2
    local method=${3:-GET}
    local body=$4
    
    echo -e "${BLUE}Testing: ${method} ${endpoint} ${params}${NC}"
    
    if [ "$method" = "GET" ]; then
        curl -s -u "${AUTH}" -X GET "${BASE_URL}${endpoint}${params}" | json_pp
    else
        curl -s -u "${AUTH}" -X ${method} \
             -H "Content-Type: application/json" \
             -d "${body}" \
             "${BASE_URL}${endpoint}" | json_pp
    fi
    
    echo -e "\n"
}

# Test 1: Hostname search (GET)
echo -e "${GREEN}Test 1: Hostname search${NC}"
call_api "/api/search" "?type=hostname&q=CCAP*"

# Test 2: IP search with Netshot integration (GET)
echo -e "${GREEN}Test 2: IP search with Netshot integration${NC}"
call_api "/api/search" "?type=ip&q=10.0.0.1"

# Test 3: IP wildcard search (GET)
echo -e "${GREEN}Test 3: IP wildcard search${NC}"
call_api "/api/search" "?type=ip&q=10.0.0.*"

# Test 4: JSON body search for hostname (POST)
echo -e "${GREEN}Test 4: JSON body search for hostname${NC}"
BODY='{
    "Header": {
        "BusinessTransactionID": "1",
        "SentTimestamp": "2023-11-10T09:20:00",
        "SourceContext": {
            "host": "TestScript",
            "application": "Bash"
        }
    },
    "Body": {
        "HostName": "GV-RC0052-CCAP002"
    }
}'
call_api "/api/search" "" "POST" "$BODY"

# Test 5: JSON body search for IP with Netshot integration (POST)
echo -e "${GREEN}Test 5: JSON body search for IP with Netshot integration${NC}"
BODY='{
    "Header": {
        "BusinessTransactionID": "2",
        "SentTimestamp": "2023-11-10T09:25:00",
        "SourceContext": {
            "host": "TestScript",
            "application": "Bash"
        }
    },
    "Body": {
        "IPAddress": "10.0.0.1"
    }
}'
call_api "/api/search" "" "POST" "$BODY"

# Test 6: Test clean URLs (GET)
echo -e "${GREEN}Test 6: Test clean URLs${NC}"
call_api "/api/search" "?type=hostname&q=CCAP*"

echo -e "${BLUE}All tests completed${NC}"
