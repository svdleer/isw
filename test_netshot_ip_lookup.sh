#!/bin/bash
# Test IP search via Netshot

# Define colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Define base URL - change as needed
BASE_URL="http://localhost/isw"
AUTH="isw:Spyem_OtGheb4"

echo -e "${BLUE}CMDB API Test Script - Netshot IP Lookup${NC}"
echo -e "${BLUE}=====================================${NC}\n"

# Test 1: IP search (GET)
echo -e "${GREEN}Test 1: IP search via Netshot${NC}"
curl -s -u "${AUTH}" -X GET "${BASE_URL}/api/search?type=ip&q=10.0.0.1" | json_pp

echo -e "\n${GREEN}Test 2: IP wildcard search via Netshot${NC}"
curl -s -u "${AUTH}" -X GET "${BASE_URL}/api/search?type=ip&q=10.0.0.*" | json_pp

echo -e "\n${GREEN}Test 3: JSON body IP search${NC}"
curl -s -u "${AUTH}" -X POST \
     -H "Content-Type: application/json" \
     -d '{
         "Header": {
             "BusinessTransactionID": "1",
             "SentTimestamp": "2023-07-23T09:20:00",
             "SourceContext": {
                 "host": "TestScript",
                 "application": "Bash"
             }
         },
         "Body": {
             "IPAddress": "10.0.0.1"
         }
     }' \
     "${BASE_URL}/api/search" | json_pp

echo -e "\n${BLUE}Test completed${NC}"
