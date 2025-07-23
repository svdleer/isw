# Enhanced Search API with Netshot Integration

This guide describes how to use the enhanced search API with Netshot integration.

## Overview

The CMDB API has been enhanced with Netshot API integration, allowing you to get additional device information when performing IP address searches. The integration works transparently - your existing API calls will automatically return additional Netshot data when available.

## Authentication

The API now uses HTTP Basic Authentication:

- Username: `isw`
- Password: `Spyem_OtGheb4`

## Search Methods

### Method 1: GET Requests

```
GET /api/search?type=ip&q=10.0.0.1
```

### Method 2: JSON Body POST Requests

```
POST /api/search
Content-Type: application/json
{
  "Header": {
    "BusinessTransactionID": "1",
    "SentTimestamp": "2023-11-10T09:20:00",
    "SourceContext": {
      "host": "String",
      "application": "String"
    }
  },
  "Body": {
    "IPAddress": "10.0.0.1"
  }
}
```

## Enhanced Response Format

When a device is found in Netshot, the API response will include additional information in a `netshot` field:

```json
{
  "status": 200,
  "search_type": "ip",
  "query": "10.0.0.1",
  "count": 1,
  "data": [
    {
      "hostname": "GV-RC0011-CCAP003",
      "ip_address": "10.0.0.1",
      "description": "Core router",
      "created_at": "2023-01-01 12:00:00",
      "updated_at": "2023-06-15 09:30:00",
      "location": "Datacenter 1",
      "netshot": {
        "id": 1234,
        "name": "GV-RC0011-CCAP003",
        "ip": "10.0.0.1",
        "model": "ASR-9000",
        "vendor": "Cisco",
        "status": "up",
        "software_version": "IOS-XR 7.3.2",
        "last_check": "2023-11-10T14:25:30"
      }
    }
  ]
}
```

## Fallback Behavior

If a device is not found in the local CMDB database but exists in Netshot, the API will still return it with available Netshot data, ensuring you get the most complete information possible.

## IP Address Field in Netshot

The API looks for IP addresses in Netshot devices using the following field names (in order of preference):
- `mgmtAddress`
- `mgmtIp`
- `managementIp`
- `ip`
- `ipAddress`
- `address`
- `primaryIp`

If none of these fields contain a valid IP address, the system will fall back to generating an IP address algorithmically based on the hostname pattern. Note that the database loopbackip field is not used as it typically contains outdated or unreliable information.

## Performance Optimization

The API includes several optimizations to ensure fast response times:

1. **Single API Call**: All Netshot devices are retrieved in a single API call instead of making individual requests for each device.

2. **Optimized Queries**: Special wildcard searches like `*CCAP*` use optimized SQL queries to improve database performance.

3. **Limited Processing**: For large result sets (over 50 devices), the API intelligently limits Netshot data processing to the most relevant devices.

4. **Direct Data Access**: The API directly accesses data sources without caching overhead to ensure data is always current.

## Example Usage

### cURL Example

```bash
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=ip&q=10.0.0.1"
```

### PHP Example

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/isw/api/search?type=ip&q=10.0.0.1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "isw:Spyem_OtGheb4");
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

// Access Netshot data if available
if (!empty($data['data'][0]['netshot'])) {
  $netshotData = $data['data'][0]['netshot'];
  echo "Device model: " . $netshotData['model'];
}
```

### JavaScript/Fetch Example

```javascript
fetch("http://localhost/isw/api/search?type=ip&q=10.0.0.1", {
  headers: {
    "Authorization": "Basic " + btoa("isw:Spyem_OtGheb4")
  }
})
.then(response => response.json())
.then(data => {
  if (data.data[0]?.netshot) {
    console.log("Device model:", data.data[0].netshot.model);
  }
});
```
