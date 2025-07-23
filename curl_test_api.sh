#!/bin/bash
# ISW CMDB API Curl Test Script
# This script tests the ISW CMDB API with various JSON body scenarios using curl

# Base URL - modify as needed
BASE_URL="http://localhost/isw/api/search"

# Auth credentials
AUTH="admin:password"

echo "===== ISW CMDB API Curl Test Script ====="
echo "Testing with JSON request bodies"
echo "=================================="

# Test 1: Exact hostname search
echo ""
echo "### TEST 1: Exact hostname search ###"
cat << EOF > test1.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test1.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test1.json
echo ""
echo "--------------------------------"

# Test 2: Wildcard hostname search (all CCAP devices)
echo ""
echo "### TEST 2: Wildcard hostname search (all CCAP devices) ###"
cat << EOF > test2.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test2.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test2.json
echo ""
echo "--------------------------------"

# Test 3: Partial hostname search with CCAP
echo ""
echo "### TEST 3: Partial hostname search with CCAP ###"
cat << EOF > test3.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test3.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test3.json
echo ""
echo "--------------------------------"

# Test 4: Partial hostname search (auto-adds CCAP)
echo ""
echo "### TEST 4: Partial hostname search (auto-adds CCAP) ###"
cat << EOF > test4.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test4.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test4.json
echo ""
echo "--------------------------------"

# Test 5: Complex wildcard hostname search
echo ""
echo "### TEST 5: Complex wildcard hostname search ###"
cat << EOF > test5.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test5.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test5.json
echo ""
echo "--------------------------------"

# Test 6: Exact IP address search
echo ""
echo "### TEST 6: Exact IP address search ###"
cat << EOF > test6.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test6.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test6.json
echo ""
echo "--------------------------------"

# Test 7: Wildcard IP search (last octet)
echo ""
echo "### TEST 7: Wildcard IP search (last octet) ###"
cat << EOF > test7.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test7.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test7.json
echo ""
echo "--------------------------------"

# Test 8: Wildcard IP search (third octet)
echo ""
echo "### TEST 8: Wildcard IP search (third octet) ###"
cat << EOF > test8.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test8.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test8.json
echo ""
echo "--------------------------------"

# Test 9: Multiple wildcards in IP search
echo ""
echo "### TEST 9: Multiple wildcards in IP search ###"
cat << EOF > test9.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test9.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test9.json
echo ""
echo "--------------------------------"

# Error test cases
echo ""
echo "===== Error Test Cases ====="

# Test 10: Missing required body field
echo ""
echo "### TEST 10: Missing required body field ###"
cat << EOF > test10.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test10.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test10.json
echo ""
echo "--------------------------------"

# Test 11: Invalid IP address format
echo ""
echo "### TEST 11: Invalid IP address format ###"
cat << EOF > test11.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test11.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test11.json
echo ""
echo "--------------------------------"

# Test 12: Invalid field name
echo ""
echo "### TEST 12: Invalid field name ###"
cat << EOF > test12.json
{
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
}
EOF

echo "curl -X POST $BASE_URL -u \"$AUTH\" -H \"Content-Type: application/json\" -d @test12.json"
curl -X POST "$BASE_URL" -u "$AUTH" -H "Content-Type: application/json" -d @test12.json
echo ""
echo "--------------------------------"

echo ""
echo "===== Tests Complete ====="
echo "Cleaning up JSON files..."
rm test*.json
echo "Done!"
