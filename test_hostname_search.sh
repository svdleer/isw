#!/bin/bash
# Test hostname search with updated API

# Define colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Define base URL - change as needed
BASE_URL="http://localhost/isw"
AUTH="isw:Spyem_OtGheb4"

echo -e "${BLUE}CMDB API Test Script - Hostname Search Only${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Test 1: Hostname search (GET)
echo -e "${GREEN}Test 1: Hostname search${NC}"
curl -s -u "${AUTH}" -X GET "${BASE_URL}/api/search?type=hostname&q=CCAP*" | json_pp

echo -e "\n${BLUE}Test completed${NC}"
