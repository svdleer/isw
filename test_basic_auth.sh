#!/bin/bash
# Test script for the JSON body search API with Basic Authentication

API_URL="http://localhost/isw/api/search"
USERNAME="admin"
PASSWORD="password"
AUTH_HEADER="Authorization: Basic $(echo -n $USERNAME:$PASSWORD | base64)"

# Test 1: GET request with Basic Authentication
echo "Test 1: GET request with Basic Authentication"
curl -X GET \
  -H "$AUTH_HEADER" \
  "$API_URL?type=hostname&q=GV-RC0011-CCAP003" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 2: POST request with JSON body and Basic Authentication
echo "Test 2: POST request with JSON body and Basic Authentication"
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

# Test 3: GET request without authentication (should fail)
echo "Test 3: GET request without authentication (should fail)"
curl -X GET \
  "$API_URL?type=hostname&q=GV-RC0011-CCAP003" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 4: POST request with JSON body but without authentication (should fail)
echo "Test 4: POST request with JSON body but without authentication (should fail)"
curl -X POST \
  -H "Content-Type: application/json" \
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
