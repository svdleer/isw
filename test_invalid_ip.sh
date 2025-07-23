#!/bin/bash
# Test script for the JSON body search API with invalid IP address testing

API_URL="http://localhost/isw/api/search"
API_KEY="your-api-key-here"

# Test 1: Valid IP address search
echo "Test 1: Valid IP address search"
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

# Test 2: Invalid IP address - too many octets
echo "Test 2: Invalid IP address - too many octets"
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
      "IPAddress": "172.16.55.26.30"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 3: Invalid IP address - octet value too large
echo "Test 3: Invalid IP address - octet value too large"
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
      "IPAddress": "172.16.55.256"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 4: Invalid IP address - not enough octets
echo "Test 4: Invalid IP address - not enough octets"
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
      "IPAddress": "172.16.55"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 5: Invalid IP address - non-numeric characters
echo "Test 5: Invalid IP address - non-numeric characters"
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
      "IPAddress": "172.16.abc.26"
    }
  }' \
  "$API_URL" | jq .

echo ""
echo "------------------------------"
echo ""

# Test 6: Valid IP address with wildcard
echo "Test 6: Valid IP address with wildcard"
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

echo ""
echo "------------------------------"
echo ""

# Test 7: Invalid IP address - leading zeros (not valid in IPv4)
echo "Test 7: Invalid IP address - leading zeros"
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
      "IPAddress": "172.016.055.026"
    }
  }' \
  "$API_URL" | jq .
