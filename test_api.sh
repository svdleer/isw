#!/bin/bash

# Test script for ISW CMDB API
# Make sure to update the API_KEY and BASE_URL variables
# Or set them as environment variables: export API_KEY="your-key" && export BASE_URL="http://localhost/isw"
# Use API keys from the admin dashboard or default development keys

API_KEY="${API_KEY:-dev-key-12345}"  # Default development key from database
BASE_URL="${BASE_URL:-http://localhost/isw}"

echo "=== ISW CMDB API Test Script ==="
echo "Base URL: $BASE_URL"
echo "API Key: $API_KEY"
echo ""

# Test 1: API Health Check
echo "1. Testing API Health Check..."
curl -s "$BASE_URL/api/health" | jq '.'
echo ""

# Test 2: Hostname exact search
echo "2. Testing hostname exact search (GV-RC0011-CCAP003)..."
curl -s "$BASE_URL/api/search?type=hostname&q=GV-RC0011-CCAP003&api_key=$API_KEY" | jq '.'
echo ""

# Test 3: Hostname wildcard search
echo "3. Testing hostname wildcard search (CCAP*)..."
curl -s "$BASE_URL/api/search?type=hostname&q=CCAP*&api_key=$API_KEY" | jq '.'
echo ""

# Test 4: IP exact search
echo "4. Testing IP exact search (192.168.1.100)..."
curl -s "$BASE_URL/api/search?type=ip&q=192.168.1.100&api_key=$API_KEY" | jq '.'
echo ""

# Test 5: IP wildcard search
echo "5. Testing IP wildcard search (192.168.1.*)..."
curl -s "$BASE_URL/api/search?type=ip&q=192.168.1.*&api_key=$API_KEY" | jq '.'
echo ""

# Test 6: Invalid API key
echo "6. Testing invalid API key..."
curl -s "$BASE_URL/api/search?type=hostname&q=CCAP*&api_key=invalid-key" | jq '.'
echo ""

# Test 7: Invalid hostname format
echo "7. Testing invalid hostname format..."
curl -s "$BASE_URL/api/search?type=hostname&q=invalid-hostname&api_key=$API_KEY" | jq '.'
echo ""

# Test 8: Invalid IP format
echo "8. Testing invalid IP format..."
curl -s "$BASE_URL/api/search?type=ip&q=300.300.300.300&api_key=$API_KEY" | jq '.'
echo ""

echo "=== Test completed ==="
