#!/bin/bash
# Test script for the updated search API

API_URL="http://localhost/isw/api/search"
USERNAME="admin"
PASSWORD="password"
AUTH_HEADER="Authorization: Basic $(echo -n $USERNAME:$PASSWORD | base64)"

# Test 1: Hostname search with CCAP
echo "Test 1: Hostname search with CCAP"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=hostname&q=CCAP" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 2: Hostname search with wildcard (should return all CCAP devices)
echo "Test 2: Hostname search with wildcard (should return all CCAP devices)"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=hostname&q=*" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 3: Hostname search with partial name
echo "Test 3: Hostname search with partial name"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=hostname&q=GV-RC" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 4: IP search (should only return active devices)
echo "Test 4: IP search (should only return active devices)"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=ip&q=192.168.1.*" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 5: JSON body hostname search
echo "Test 5: JSON body hostname search"
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
      "HostName": "*"
    }
  }' \
  "$API_URL" | jq .
