# ISW CMDB PHP REST API

A PHP REST API for querying a MySQL CMDB database with support for hostname and IP address searches using HTTP Basic Authentication.

## Features

- **HTTP Basic Authentication**: Secure access with username/password authentication
- **Admin Dashboard**: Web-based admin tool for managing API access
- **Interactive API Documentation**: Swagger/OpenAPI documentation with live testing
- **Hostname Search**: Search for devices using hostname patterns (4char-2char4num-CCAPxxx format)
- **IP Address Search**: Search for devices using IP addresses
- **Wildcard Support**: Both hostname and IP searches support wildcards (* or %)
- **JSON Body Searches**: Support for JSON-formatted search requests
- **JSON Responses**: All responses are in JSON format
- **Error Handling**: Comprehensive error handling with appropriate HTTP status codes
- **Netshot API Integration**: Enhanced device information from Netshot API
- **Response Caching**: Cached Netshot API responses for improved performance
- **Clean URL Support**: API accessible without .php extensions via .htaccess

## API Endpoints

### Search Devices
`GET /api/search`

**Parameters:**
- `type`: Search type (`hostname` or `ip`)
- `q`: Search query
- `api_key`: Valid API key

### Health Check
`GET /api/health`

Returns API health status.

### API Documentation
`GET /docs/`

Interactive Swagger/OpenAPI documentation with live API testing.

## API Documentation

The API includes comprehensive Swagger/OpenAPI documentation:

- **Interactive Documentation**: Navigate to `/docs/` for full Swagger UI
- **Live Testing**: Test API endpoints directly from the documentation
- **Complete Schemas**: Detailed request/response schemas and examples
- **Multiple Formats**: Available in both YAML (`/docs/openapi.yaml`) and JSON (`/docs/openapi.json`) formats

**Access the documentation at:** `http://localhost/isw/docs/`

## Hostname Search Format

Hostnames must follow the pattern: `4char-2char4num-CCAPxxx`
- 4char: 2 letters (a-z, case insensitive)
- 2char4num: 2 letters followed by 4 numbers
- CCAPxxx: Must start with "CCAP", followed by alphanumeric characters

**Examples:**
- `GV-RC0011-CCAP003` (exact search)
- `CCAP*` or `CCAP%` (wildcard search for all CCAP devices)

## IP Address Search

Supports standard IPv4 addresses with optional wildcards.

**Examples:**
- `192.168.1.100` (exact search)
- `192.168.1.*` or `192.168.1.%` (wildcard search)

## Usage Examples

### GET Requests

```bash
# Exact hostname search with HTTP Basic Auth
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=hostname&q=GV-RC0011-CCAP003"

# Wildcard hostname search
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=hostname&q=CCAP*"

# Exact IP search
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=ip&q=192.168.1.100"

# Wildcard IP search
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=ip&q=192.168.1.*"
```

### POST Requests with JSON Body

```bash
# Hostname search with JSON body
curl -X POST -u "isw:Spyem_OtGheb4" \
  -H "Content-Type: application/json" \
  -d '{
    "Header": {
      "BusinessTransactionID": "1",
      "SentTimestamp": "2023-11-10T09:20:00",
      "SourceContext": {
        "host": "ExampleSystem",
        "application": "ServicePortal"
      }
    },
    "Body": {
      "HostName": "GV-RC0052-CCAP002"
    }
  }' \
  "http://localhost/isw/api/search"

# IP search with JSON body
curl -X POST -u "isw:Spyem_OtGheb4" \
  -H "Content-Type: application/json" \
  -d '{
    "Header": {
      "BusinessTransactionID": "1",
      "SentTimestamp": "2023-11-10T09:20:00",
      "SourceContext": {
        "host": "ExampleSystem",
        "application": "ServicePortal"
      }
    },
    "Body": {
      "IPAddress": "172.16.55.26"
    }
  }' \
  "http://localhost/isw/api/search"
```

### Clean URL Examples (without .php extension)

```bash
# Using clean URLs without .php extension
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=hostname&q=CCAP*"
```

## Netshot API Integration

The API now integrates with Netshot to provide additional device information when performing IP searches:

1. **Enhanced Device Information**: Additional fields like model, vendor, software version
2. **Caching**: Netshot API responses are cached to improve performance
3. **Fallback Support**: If a device isn't in the local database but exists in Netshot, it will still be returned
4. **Seamless Integration**: No changes to API request format required

**Example Response with Netshot Data:**

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

## Installation

1. **Database Setup**
   ```bash
   # Use the updated schema with admin functionality
   mysql -u root -p < database/schema_with_admin.sql
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials and Netshot API information
   ```

3. **Web Server Configuration**
   - Ensure PHP 7.4+ with PDO MySQL extension
   - Configure web server to serve from project root
   - Ensure .htaccess is processed (Apache) or configure URL rewriting

4. **Admin Access**
   - Navigate to `/admin/` in your browser
   - Default login: `admin` / `admin123`
   - **Important**: Change the default password immediately after first login

## Admin Dashboard

The admin dashboard provides a web interface for managing API keys:

- **Access**: Navigate to `http://your-domain/isw/admin/`
- **Default Credentials**: 
  - Username: `admin`
  - Password: `admin123`
- **Features**:
  - Create new API keys with optional expiration dates
  - Enable/disable existing API keys
  - View usage statistics and last used dates
  - Delete API keys
  - Copy API keys to clipboard

### Admin Dashboard Screenshots

The admin interface includes:
- Dashboard with API key statistics
- Create new API key form with name, description, and expiration
- API key management table with actions
- Responsive design using Tailwind CSS

## File Structure

```
isw/
├── admin/                  # Admin dashboard
│   ├── index.php          # Main admin dashboard
│   ├── login.php          # Admin login page
│   ├── logout.php         # Admin logout handler
│   └── .htaccess          # Admin security config
├── api/
│   └── search.php          # Main search endpoint
├── classes/
│   ├── Database.php        # Database connection class
│   ├── ApiAuth.php         # Authentication and validation
│   ├── AdminAuth.php       # Admin authentication
│   ├── ApiKeyManager.php   # API key management
│   └── EnvLoader.php       # Environment variable loader
├── config/
│   └── database.php        # Database configuration
├── database/
│   ├── schema.sql          # Basic database schema
│   └── schema_with_admin.sql # Enhanced schema with admin
├── docs/                   # API Documentation
│   ├── index.html          # Swagger UI interface
│   ├── openapi.yaml        # OpenAPI specification (YAML)
│   ├── openapi.json        # OpenAPI specification (JSON)
│   └── .htaccess          # Documentation access config
├── index.php               # Main router
├── .htaccess              # Apache configuration
├── .env                   # Environment variables (create from .env.example)
├── .env.example           # Environment template
├── .gitignore             # Git ignore rules
├── test_api.sh            # API test script
└── README.md              # This file
```

## Testing

Run the test script to verify API functionality:

```bash
chmod +x test_api.sh
./test_api.sh
```

## Security Notes

- Store API keys securely (environment variables recommended)
- Use HTTPS in production
- Implement rate limiting for production use
- Regular security audits recommended
- Consider implementing API key expiration and rotation

## Error Codes

- `200`: Success
- `400`: Bad Request (invalid parameters or format)
- `401`: Unauthorized (invalid or missing API key)
- `404`: Not Found (endpoint doesn't exist)
- `405`: Method Not Allowed
- `500`: Internal Server Error
