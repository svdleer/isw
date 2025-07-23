# ISW CMDB API Documentation

## Overview
This API provides access to device information from the ISW CMDB system. It supports querying devices by hostname or IP address, with wildcard pattern support for both search types.

## Authentication
The API uses HTTP Basic Authentication. Provide your username and password in the request header.

Example:
```
Authorization: Basic YWRtaW46cGFzc3dvcmQ=
```
(This is the base64 encoding of "admin:password")

## Endpoints

### Search Endpoint
`POST /api/search`

Searches for devices by hostname or IP address.

#### Request Format
The API accepts JSON request bodies with the following structure:

```json
{
  "Header": {
    "BusinessTransactionID": "string",
    "SentTimestamp": "ISO8601 timestamp",
    "SourceContext": {
      "host": "string",
      "application": "string"
    }
  },
  "Body": {
    "HostName": "string" | "IPAddress": "string"
  }
}
```

- You must include either `HostName` or `IPAddress` in the Body (but not both)
- The Header section is required for tracking and logging purposes

#### Response Format
The API returns JSON responses with the following structure:

```json
{
  "Header": {
    "BusinessTransactionID": "string",
    "SentTimestamp": "ISO8601 timestamp",
    "SourceContext": {
      "host": "string", 
      "application": "string"
    },
    "Status": {
      "Code": "number",
      "Description": "string"
    }
  },
  "Body": [
    {
      "HostName": "string",
      "IPAddress": "string",
      "Description": "string",
      "Status": "string"
    },
    ...
  ]
}
```

## Search Patterns

### Hostname Search
- Exact match: `GV-RC0011-CCAP003`
- All CCAP devices: `*`
- Partial match with CCAP: `CCAP*`
- Partial match (auto-adds CCAP): `GV-RC`
- Complex pattern: `*CCAP*003`

Note: If the hostname doesn't contain "CCAP", the API will automatically append it to ensure compliance with naming conventions.

### IP Address Search
- Exact match: `172.16.55.26`
- Wildcard in last octet: `172.16.55.*`
- Wildcard in third octet: `172.16.*.26`
- Multiple wildcards: `172.*.*.26`

## Error Handling
The API returns appropriate HTTP status codes and error messages:

- 400: Bad Request (invalid input)
- 401: Unauthorized (authentication failed)
- 404: Not Found (no matching records)
- 500: Internal Server Error

Error responses include details in the Header.Status section of the JSON response.

## Example Requests

### Hostname Search
```bash
curl -X POST http://localhost/isw/api/search \
  -u admin:password \
  -H "Content-Type: application/json" \
  -d '{
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
```

> Note: The API now supports clean URLs without the `.php` extension. You can use both `/api/search.php` and `/api/search` in your requests.

### IP Address Search with Wildcard
```bash
curl -X POST http://localhost/isw/api/search \
  -u admin:password \
  -H "Content-Type: application/json" \
  -d '{
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
```

## Tools and Resources

- **Postman Collection**: Import `ISW_CMDB_API_Postman_Collection.json` into Postman to quickly test the API.
- **PowerShell Test Script**: Run `test_json_api.ps1` to execute a suite of test cases against the API.
- **Test JSON Bodies**: Sample request bodies are available in `test_json_bodies.md` for reference.
- **OpenAPI Documentation**: Available at `/docs/` endpoint for interactive API exploration.
