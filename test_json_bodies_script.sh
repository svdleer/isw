#!/bin/bash
# Test script for ISW CMDB API using different JSON bodies

API_URL="http://localhost/isw/api/search"
USERNAME="admin"
PASSWORD="password"
AUTH_HEADER="Authorization: Basic $(echo -n $USERNAME:$PASSWORD | base64)"

# Text colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== ISW CMDB API Test Script ===${NC}\n"

# Function to run a test
run_test() {
  test_number=$1
  test_description=$2
  json_body=$3

  echo -e "${BLUE}Test $test_number: $test_description${NC}"
  echo -e "${GREEN}Request Body:${NC}"
  echo "$json_body" | jq .
  echo
  
  response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -H "$AUTH_HEADER" \
    -d "$json_body" \
    "$API_URL")
  
  echo -e "${GREEN}Response:${NC}"
  echo "$response" | jq .
  
  echo -e "\n${BLUE}--------------------------------${NC}\n"
}

# Test 1: Exact hostname search
run_test 1 "Exact hostname search" '{
  "Header": {
    "BusinessTransactionID": "1",
    "SentTimestamp": "2023-11-10T09:20:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "HostName": "GV-RC0011-CCAP003"
  }
}'

# Test 2: Wildcard hostname search (all CCAP devices)
run_test 2 "Wildcard hostname search (all CCAP devices)" '{
  "Header": {
    "BusinessTransactionID": "2",
    "SentTimestamp": "2023-11-10T09:30:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "HostName": "*"
  }
}'

# Test 3: Partial hostname search with CCAP
run_test 3 "Partial hostname search with CCAP" '{
  "Header": {
    "BusinessTransactionID": "3",
    "SentTimestamp": "2023-11-10T09:40:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "HostName": "CCAP*"
  }
}'

# Test 4: Partial hostname search (auto-adds CCAP)
run_test 4 "Partial hostname search (auto-adds CCAP)" '{
  "Header": {
    "BusinessTransactionID": "4",
    "SentTimestamp": "2023-11-10T09:50:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "HostName": "GV-RC"
  }
}'

# Test 5: Complex wildcard hostname search
run_test 5 "Complex wildcard hostname search" '{
  "Header": {
    "BusinessTransactionID": "5",
    "SentTimestamp": "2023-11-10T10:00:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "HostName": "*CCAP*003"
  }
}'

# Test 6: Exact IP address search
run_test 6 "Exact IP address search" '{
  "Header": {
    "BusinessTransactionID": "6",
    "SentTimestamp": "2023-11-10T10:10:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "IPAddress": "172.16.55.26"
  }
}'

# Test 7: Wildcard IP search (last octet)
run_test 7 "Wildcard IP search (last octet)" '{
  "Header": {
    "BusinessTransactionID": "7",
    "SentTimestamp": "2023-11-10T10:20:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "IPAddress": "172.16.55.*"
  }
}'

# Test 8: Wildcard IP search (third octet)
run_test 8 "Wildcard IP search (third octet)" '{
  "Header": {
    "BusinessTransactionID": "8",
    "SentTimestamp": "2023-11-10T10:30:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "IPAddress": "172.16.*.26"
  }
}'

# Test 9: Multiple wildcards in IP search
run_test 9 "Multiple wildcards in IP search" '{
  "Header": {
    "BusinessTransactionID": "9",
    "SentTimestamp": "2023-11-10T10:40:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "IPAddress": "172.*.*.26"
  }
}'

# Test 10: Missing required body field (Error test)
run_test 10 "Missing required body field (Error test)" '{
  "Header": {
    "BusinessTransactionID": "10",
    "SentTimestamp": "2023-11-10T10:50:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
  }
}'

# Test 11: Invalid IP address format (Error test)
run_test 11 "Invalid IP address format (Error test)" '{
  "Header": {
    "BusinessTransactionID": "11",
    "SentTimestamp": "2023-11-10T11:00:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "IPAddress": "172.16.256.1"
  }
}'

# Test 12: Malformed JSON structure (Error test)
run_test 12 "Malformed JSON structure (Error test)" '{
  "Header": {
    "BusinessTransactionID": "12",
    "SentTimestamp": "2023-11-10T11:10:00",
    "SourceContext": {
      "host": "TestServer",
      "application": "ApiTester"
    }
  },
  "Body": {
    "InvalidField": "GV-RC0011-CCAP003"
  }
}'

echo -e "${BLUE}All tests completed!${NC}"
