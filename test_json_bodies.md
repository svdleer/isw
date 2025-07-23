# ISW CMDB API Test Bodies

This file contains various test JSON bodies that can be used to test the API's POST endpoints.

## Basic Authentication Header

```
Authorization: Basic YWRtaW46cGFzc3dvcmQ=
```

This header represents the Base64 encoding of `admin:password`. Replace with your own credentials if needed.

## Hostname Search Test Bodies

### 1. Exact hostname search

```json
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
```

### 2. Wildcard hostname search (all CCAP devices)

```json
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
```

### 3. Partial hostname search with CCAP

```json
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
```

### 4. Partial hostname search (auto-adds CCAP)

```json
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
```

### 5. Complex wildcard hostname search

```json
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
```

## IP Address Search Test Bodies

### 1. Exact IP address search

```json
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
```

### 2. Wildcard IP search (last octet)

```json
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
```

### 3. Wildcard IP search (third octet)

```json
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
```

### 4. Multiple wildcards in IP search

```json
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
```

## Invalid Test Bodies (For Error Testing)

### 1. Missing required body field

```json
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
```

### 2. Invalid IP address format

```json
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
```

### 3. Malformed JSON structure

```json
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
```
