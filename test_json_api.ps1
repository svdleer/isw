# ISW CMDB API Test Script for Windows PowerShell
# This script tests the ISW CMDB API with various JSON body scenarios

# Base URL - modify as needed
$baseUrl = "http://localhost/isw/api/search"

# Auth credentials
$username = "admin"
$password = "password"
$base64AuthInfo = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes(("{0}:{1}" -f $username, $password)))

Write-Host "===== ISW CMDB API Test Script =====" -ForegroundColor Green
Write-Host "Testing with JSON request bodies" -ForegroundColor Green
Write-Host "==================================" -ForegroundColor Green

# Function to make a POST request with JSON body
function Test-PostRequest {
    param (
        [string]$testName,
        [string]$jsonBody
    )
    
    Write-Host ""
    Write-Host "### TEST: $testName ###" -ForegroundColor Yellow
    Write-Host "Request Body:" -ForegroundColor Cyan
    Write-Host $jsonBody
    Write-Host ""
    Write-Host "Response:" -ForegroundColor Cyan
    
    $headers = @{
        "Authorization" = "Basic $base64AuthInfo"
        "Content-Type" = "application/json"
    }
    
    try {
        $response = Invoke-RestMethod -Uri $baseUrl -Method Post -Headers $headers -Body $jsonBody -ErrorAction Stop
        $responseJson = $response | ConvertTo-Json -Depth 10
        Write-Host $responseJson
    }
    catch {
        Write-Host "Error: $_" -ForegroundColor Red
        Write-Host $_.Exception.Response.StatusCode.value__
        if ($_.ErrorDetails.Message) {
            Write-Host $_.ErrorDetails.Message
        }
    }
    
    Write-Host "--------------------------------" -ForegroundColor DarkGray
}

# Test 1: Exact hostname search
Write-Host "Test 1: Exact hostname search" -ForegroundColor Green
Test-PostRequest -testName "Exact hostname search" -jsonBody @'
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
'@

# Test 2: Wildcard hostname search (all CCAP devices)
Write-Host "Test 2: Wildcard hostname search (all CCAP devices)" -ForegroundColor Green
Test-PostRequest -testName "Wildcard hostname search (all CCAP devices)" -jsonBody @'
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
'@

# Test 3: Partial hostname search with CCAP
Write-Host "Test 3: Partial hostname search with CCAP" -ForegroundColor Green
Test-PostRequest -testName "Partial hostname search with CCAP" -jsonBody @'
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
'@

# Test 4: Partial hostname search (auto-adds CCAP)
Write-Host "Test 4: Partial hostname search (auto-adds CCAP)" -ForegroundColor Green
Test-PostRequest -testName "Partial hostname search (auto-adds CCAP)" -jsonBody @'
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
'@

# Test 5: Complex wildcard hostname search
Write-Host "Test 5: Complex wildcard hostname search" -ForegroundColor Green
Test-PostRequest -testName "Complex wildcard hostname search" -jsonBody @'
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
'@

# Test 6: Exact IP address search
Write-Host "Test 6: Exact IP address search" -ForegroundColor Green
Test-PostRequest -testName "Exact IP address search" -jsonBody @'
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
'@

# Test 7: Wildcard IP search (last octet)
Write-Host "Test 7: Wildcard IP search (last octet)" -ForegroundColor Green
Test-PostRequest -testName "Wildcard IP search (last octet)" -jsonBody @'
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
'@

# Test 8: Wildcard IP search (third octet)
Write-Host "Test 8: Wildcard IP search (third octet)" -ForegroundColor Green
Test-PostRequest -testName "Wildcard IP search (third octet)" -jsonBody @'
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
'@

# Test 9: Multiple wildcards in IP search
Write-Host "Test 9: Multiple wildcards in IP search" -ForegroundColor Green
Test-PostRequest -testName "Multiple wildcards in IP search" -jsonBody @'
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
'@

# Error test cases
Write-Host ""
Write-Host "===== Error Test Cases =====" -ForegroundColor Red

# Test 10: Missing required body field
Write-Host "Test 10: Missing required body field" -ForegroundColor Green
Test-PostRequest -testName "Missing required body field" -jsonBody @'
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
'@

# Test 11: Invalid IP address format
Write-Host "Test 11: Invalid IP address format" -ForegroundColor Green
Test-PostRequest -testName "Invalid IP address format" -jsonBody @'
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
'@

# Test 12: Invalid field name
Write-Host "Test 12: Invalid field name" -ForegroundColor Green
Test-PostRequest -testName "Invalid field name" -jsonBody @'
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
'@

Write-Host ""
Write-Host "===== Tests Complete =====" -ForegroundColor Green
Write-Host "Press any key to exit..." -ForegroundColor Yellow
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
