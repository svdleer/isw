#!/bin/bash
# Test script for the cross-database search functionality

API_URL="http://localhost/isw/api/search"
USERNAME="admin"
PASSWORD="password"
AUTH_HEADER="Authorization: Basic $(echo -n $USERNAME:$PASSWORD | base64)"

# Test 1: Hostname search with cross-database query
echo "Test 1: Hostname search with cross-database query"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=hostname&q=GV-RC0011-CCAP003" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 2: IP search with cross-database query
echo "Test 2: IP search with cross-database query"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=ip&q=192.168.1.100" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 3: POST request with hostname search
echo "Test 3: POST request with hostname search"
curl -X POST \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -d '{
    "Header": {
      "BusinessTransactionID": "1",
      "SentTimestamp": "2023-11-10T09:20:00",
      "SourceContext": {
        "host": "String",
        "application": "String"
      }
    },
    "Body": {
      "HostName": "GV-RC0052-CCAP002"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 4: Wildcard hostname search
echo "Test 4: Wildcard hostname search"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=hostname&q=CCAP*" | jq .
