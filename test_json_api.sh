#!/bin/bash
# Test script for the JSON body search API

API_URL="http://localhost/isw/api/search"
API_KEY="your-api-key-here"

# Test 1: Search by hostname using JSON body
echo "Test 1: Search by hostname using JSON body"
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
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

# Test 2: Search by IP address using JSON body
echo "Test 2: Search by IP address using JSON body"
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
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
      "IPAddress": "172.16.55.26"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 3: Search by hostname with wildcard using JSON body
echo "Test 3: Search by hostname with wildcard using JSON body"
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
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
      "HostName": "CCAP*"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 4: Search by IP address with wildcard using JSON body
echo "Test 4: Search by IP address with wildcard using JSON body"
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
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
      "IPAddress": "172.16.55.*"
    }
  }' \
  "$API_URL" | jq .
