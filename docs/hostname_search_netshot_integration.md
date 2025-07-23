# Hostname Search and Netshot API Integration

This document outlines the changes made to the search functionality:

## Changes to Database Search

The API has been updated to only search by hostname or alias in the SQL database. IP address searches are now handled exclusively through the Netshot API integration.

### Reasons for the Change

1. Database schema structure differences: The `access.devicesnew` table may not have an `ip_address` column but instead uses `ipaddress` or has a different structure entirely
2. Improved reliability: By using the Netshot API for IP lookups, we get more comprehensive device information
3. Reduced database complexity: Simplifies the SQL queries by focusing on hostname searches only

## How IP Lookups Now Work

When a user performs an IP address search:

1. The request is validated to ensure it's a proper IPv4 format
2. For exact IP searches:
   - The Netshot API is queried directly with the IP address
   - If a device is found, it's returned with enhanced Netshot information
   - If not found in Netshot, the system attempts to find hostnames associated with this IP
   - Those hostnames are then used to query the database
3. For wildcard IP searches:
   - The Netshot API's `searchDevicesByIp` method is used
   - Results are returned directly from Netshot without database queries

## Hostname Search Behavior

Hostname searches remain unchanged in behavior, but the SQL query has been simplified:

1. The query now only searches by hostname and alias fields
2. The `ip_address` field is returned as an empty string in results
3. If the hostname is found in Netshot, additional information is appended

## Using the API

### GET Requests

```bash
# Hostname search
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=hostname&q=CCAP*"

# IP search (now via Netshot)
curl -u "isw:Spyem_OtGheb4" "http://localhost/isw/api/search?type=ip&q=10.0.0.1"
```

### POST Requests with JSON Body

```bash
# IP search with JSON body
curl -X POST -u "isw:Spyem_OtGheb4" \
  -H "Content-Type: application/json" \
  -d '{
    "Header": {
      "BusinessTransactionID": "1",
      "SentTimestamp": "2023-07-23T09:20:00",
      "SourceContext": {
        "host": "String",
        "application": "String"
      }
    },
    "Body": {
      "IPAddress": "10.0.0.1"
    }
  }' \
  "http://localhost/isw/api/search"
```

## Testing

The following test scripts are available:

1. `test_hostname_search.sh` - Tests hostname search functionality
2. `test_netshot_ip_lookup.sh` - Tests IP lookups via Netshot API

## Troubleshooting

If you encounter any issues:

1. Check the web server error logs for detailed error messages
2. Ensure the Netshot API is accessible and configured correctly
3. Verify the database connection and schema structure
4. Use the test scripts to diagnose specific issues
