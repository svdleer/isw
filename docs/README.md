# ISW CMDB API v2.0 - Netshot Integration

## 🚀 Overview

The ISW CMDB API v2.0 provides comprehensive device search capabilities with enhanced Netshot integration, real-time IP address resolution, and advanced alias support. The API now supports both CCAP hostnames and user-friendly alias names (ABR/DBR/CBR format) with optimized wildcard search functionality.

## ✨ Key Features

### 🔄 Netshot Integration
- **Real-time Discovery**: Direct integration with Netshot OSS for live device information
- **IP Resolution**: Automatic IP address lookup from Netshot's management interface data
- **Device Status**: Real-time status information (INPRODUCTION, MAINTENANCE, etc.)
- **Hardware Details**: Model, vendor, and software version from Netshot

### 🏷️ Alias Support
- **Bidirectional Mapping**: Automatic conversion between CCAP and alias formats
- **ABR/DBR/CBR Support**: Search using user-friendly names like `gv02abr001`
- **Database Integration**: MySQL alias table integration for hostname mapping
- **Consistent Responses**: Always returns CCAP hostname as primary with alias when available

### 🔍 Enhanced Search Capabilities
- **Optimized IP Wildcards**: Uses `\d{1,3}` patterns for precise IP octet matching
- **Memory Caching**: In-memory hostname-to-IP mapping for performance
- **REST-Compliant**: PascalCase field names (`Name`, `IpAddress`, `Alias`)
- **Flexible Patterns**: Support for `*` and `%` wildcards in both hostname and IP searches

## 📚 API Documentation

### Interactive Documentation
Visit the Swagger UI for interactive API testing:
- **Local**: `http://localhost/isw/docs/`
- **OpenAPI Spec**: `http://localhost/isw/docs/openapi.yaml`

### Quick Examples

#### 1. CCAP Hostname Search
```bash
GET /api/search?type=hostname&q=GV-RC0011-CCAP003&api_key=your-key
```

#### 2. Alias Search
```bash
GET /api/search?type=hostname&q=gv02abr001&api_key=your-key
```

#### 3. IP Wildcard Search (Enhanced)
```bash
GET /api/search?type=ip&q=172.28.88.*&api_key=your-key
```

#### 4. JSON POST Request
```bash
POST /api/search
Content-Type: application/json

{
  "Header": {
    "BusinessTransactionID": "12345",
    "SentTimestamp": "2025-07-24T06:30:00Z",
    "SourceContext": {
      "host": "statsit.example.com",
      "application": "StatsIT"
    }
  },
  "Body": {
    "HostName": "*CCAP*"
  }
}
```

## 🔧 Technical Implementation

### Netshot API Integration
```php
// Automatic Netshot device lookup with IP resolution
$netshot = new NetshotAPI();
$devices = $netshot->searchDevicesByIp('172.28.88.*');
```

### Alias Resolution
```php
// Bidirectional hostname/alias mapping
$ccapHostname = $netshot->mapAbrToCcapHostname('gv02abr001');
$aliasName = $netshot->findAliasForCcapHostname('GV-RC0011-CCAP003');
```

### Enhanced IP Wildcard Matching
```php
// Optimized regex patterns for IP addresses
private function patternToRegex($pattern) {
    $pattern = preg_quote($pattern, '/');
    $pattern = str_replace(['%', '\\*'], ['\d{1,3}', '\d{1,3}'], $pattern);
    return '/^' . $pattern . '$/i';
}
```

## 📊 Response Format

### Enhanced Device Schema (REST-Compliant)
```json
{
  "status": 200,
  "search_type": "ip",
  "query": "172.28.88.*",
  "count": 2,
  "data": [
    {
      "Id": 12345,
      "Name": "GV-RC0011-CCAP003",
      "IpAddress": "172.28.88.15",
      "Alias": "gv02abr001",
      "Model": "Casa Systems C100G",
      "Vendor": "Casa Systems",
      "Status": "INPRODUCTION",
      "SoftwareVersion": "4.2.1-RELEASE",
      "LastCheck": "2025-07-24T06:30:00Z"
    }
  ]
}
```

## 🔍 Search Patterns

### Hostname Patterns
| Pattern | Description | Example |
|---------|-------------|---------|
| `GV-RC0011-CCAP003` | Exact CCAP hostname | Single device |
| `*CCAP*` | All CCAP devices | Multiple devices |
| `GV-*` | All devices starting with GV- | Regional devices |
| `gv02abr001` | Exact alias name | Single device via alias |
| `*abr*` | All ABR aliases | Multiple alias devices |

### IP Address Patterns
| Pattern | Description | Matches |
|---------|-------------|---------|
| `172.28.88.15` | Exact IP | Single device |
| `172.28.88.*` | Subnet wildcard | 172.28.88.1-255 |
| `172.28.*.*` | Network wildcard | Entire 172.28.x.x |
| `10.*.*.*` | Class A wildcard | Entire 10.x.x.x |

## 🚦 Status Codes

| Code | Description | Response |
|------|-------------|----------|
| 200 | Success | Device data returned |
| 400 | Bad Request | Invalid parameters or format |
| 401 | Unauthorized | Invalid or missing API key |
| 500 | Server Error | Database or Netshot connection issue |

## 🔐 Authentication

### HTTP Basic Auth
```http
Authorization: Basic base64(username:password)
```

### API Key (Query Parameter)
```bash
?api_key=your-api-key-here
```

## 🔧 Configuration

### Environment Variables
```bash
NETSHOT_API_URL=https://netshot.oss.local/api
NETSHOT_API_TOKEN=your-netshot-token
NETSHOT_GROUP=207
```

### Database Configuration
```php
// Alias table structure
CREATE TABLE reporting.acc_alias (
    alias VARCHAR(255),
    ccap_name VARCHAR(255),
    INDEX idx_alias (alias),
    INDEX idx_ccap (ccap_name)
);
```

## 📈 Performance Optimizations

### Memory Caching
- In-memory hostname-to-IP mapping
- Alias-to-CCAP hostname cache
- Reduced Netshot API calls

### Indexing
- Device indexing by hostname and IP
- O(1) lookup performance for exact matches
- Optimized wildcard searches

### Connection Optimization
- Persistent Netshot API connections
- Connection pooling for database
- Timeout handling for reliability

## 🐛 Debugging

### Log Analysis
```bash
# Check IP wildcard search logs
grep "IP pattern search" /var/log/apache2/error.log

# Monitor Netshot integration
grep "NetshotAPI:" /var/log/apache2/error.log

# Alias resolution tracking
grep "alias" /var/log/apache2/error.log
```

### Common Issues
1. **No devices found**: Check Netshot connectivity and group configuration
2. **IP wildcard not working**: Verify regex pattern generation in logs
3. **Alias not resolved**: Check MySQL alias table and database connection

## 🔄 Migration from v1.0

### Field Name Changes (REST Compliance)
| v1.0 Field | v2.0 Field | Description |
|------------|------------|-------------|
| `hostname` | `Name` | Primary hostname (CCAP format) |
| `ip_address` | `IpAddress` | IP address from Netshot |
| `id` | `Id` | Netshot device ID |
| N/A | `Alias` | **NEW**: User-friendly alias name |
| N/A | `Model` | **NEW**: Device model from Netshot |
| N/A | `Vendor` | **NEW**: Device vendor from Netshot |
| `status` | `Status` | Enhanced with Netshot status |

### Backward Compatibility
- Legacy field names still included in responses
- Existing API calls continue to work
- Gradual migration recommended

## 📝 Release Notes

### v2.0.0 (2025-07-24)
- ✅ **Enhanced Netshot Integration**: Real-time device discovery and IP resolution
- ✅ **Alias Support**: ABR/DBR/CBR to CCAP hostname mapping
- ✅ **Optimized IP Wildcards**: `\d{1,3}` patterns for precise matching
- ✅ **REST-Compliant Fields**: PascalCase field names
- ✅ **Memory Caching**: In-memory hostname-to-IP mapping
- ✅ **Enhanced Documentation**: Updated OpenAPI specification and Swagger UI

### v1.0.0 (Previous)
- Basic hostname and IP search
- Simple wildcard support
- MySQL database integration

## 📞 Support

For technical support or questions about the ISW CMDB API v2.0:
- Check the interactive documentation at `/docs/`
- Review the logs for debugging information
- Verify Netshot connectivity and configuration

---
*ISW CMDB API v2.0 - Enhanced with Netshot Integration and Real-time Device Discovery*
