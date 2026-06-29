# CORS Configuration Guide

## Overview

Cross-Origin Resource Sharing (CORS) is now properly configured with explicit origin whitelist. The API **no longer accepts requests from any domain** (`Access-Control-Allow-Origin: *`).

## Security Changes

### Before (UNSAFE ❌)
```php
header("Access-Control-Allow-Origin: *");  // Any domain could access API
```

**Vulnerability:** Any website could make authenticated requests to your API if users were logged in (CSRF attack).

### After (SECURE ✅)
```
Only explicitly configured origins can access the API.
All requests from disallowed origins are rejected by the browser.
```

## Configuration

### Environment Variable

Set `CORS_ALLOWED_ORIGINS` in your `.env` file:

```bash
# Local development (multiple origins)
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080

# Production (single or multiple domains)
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com

# With subdomain wildcards
CORS_ALLOWED_ORIGINS=https://*.example.com,https://api.example.com

# Port-specific
CORS_ALLOWED_ORIGINS=https://example.com:8443
```

### Valid Formats

| Format | Example | Matches |
|--------|---------|---------|
| Exact domain | `https://example.com` | Only `https://example.com` |
| With port | `http://localhost:3000` | Only `http://localhost:3000:3000` |
| Subdomain wildcard | `https://*.example.com` | `https://app.example.com`, `https://api.example.com` |
| Multiple origins | `https://app.com,https://api.com` | Both domains |

## How It Works

1. **Client makes request** with `Origin` header (e.g., `Origin: https://app.example.com`)

2. **Server checks** if origin is in `CORS_ALLOWED_ORIGINS` list

3. **If allowed:**
   - Responds with `Access-Control-Allow-Origin: https://app.example.com`
   - Browser allows client code to read response

4. **If not allowed:**
   - No `Access-Control-Allow-Origin` header in response
   - Browser blocks client code from reading response (CORS error)

## CORS Headers Sent

For all requests (including disallowed origins):

```
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Search-Query, X-Search-Columns, X-Sort-By, X-Sort-Direction, X-Pagination-Limit, X-Pagination-Offset, X-Request-ID
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
```

For **allowed origins only**:

```
Access-Control-Allow-Origin: <the-origin>
```

## Preflight Requests

Modern browsers send an `OPTIONS` request before certain requests (POST, PUT, DELETE, or custom headers).

**The API automatically handles preflight:**
- Returns HTTP 204 No Content
- No request body needed
- Client uses preflight response to determine if actual request can proceed

## Setup Instructions

### 1. Update .env (Local Development)

```bash
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
```

### 2. Update .env (Production)

```bash
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

### 3. Verify Configuration

Make a test request:

```bash
# This should work (allowed origin)
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS \
     http://localhost:8000/health

# Should see in response:
# Access-Control-Allow-Origin: http://localhost:3000

# This should NOT see Allow-Origin header (rejected)
curl -H "Origin: http://attacker.com" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS \
     http://localhost:8000/health

# Response should NOT include:
# Access-Control-Allow-Origin
```

## Important Notes

### ⚠️ Never Use Wildcard (*)

```bash
# ❌ DO NOT DO THIS
CORS_ALLOWED_ORIGINS=*

# ✅ DO THIS INSTEAD
CORS_ALLOWED_ORIGINS=https://app.example.com,https://api.example.com
```

### ⚠️ Must Be Set in Environment

The `CORS_ALLOWED_ORIGINS` variable **must** be set. API will not start without it:

```
Exception: CORS_ALLOWED_ORIGINS environment variable is not configured.
Please set it to a comma-separated list of allowed origins...
```

### ✅ Supported Protocols

- `http://` - HTTP (local development only)
- `https://` - HTTPS (production)
- Port numbers - `http://localhost:3000`, `https://example.com:8443`
- Subdomains - `https://subdomain.example.com`
- Subdomain wildcards - `https://*.example.com`

### ❌ Not Supported

- IP addresses with wildcards: `192.168.*.1` - use full IP
- Protocol wildcards: `*://example.com` - specify `http://` or `https://`
- Path matching: `https://example.com/api` - only domain and port matched
- Bare domains without protocol: `example.com` - must include `https://`

## Testing CORS

### Using Fetch API (Browser)

```javascript
// This will work (allowed origin)
fetch('http://api.local:8000/health', {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
  }
})
.then(response => response.json())
.then(data => console.log('Success:', data))
.catch(error => console.error('CORS Error:', error));

// This will be blocked (disallowed origin)
// Browser will show: Access to XMLHttpRequest blocked by CORS policy
```

### Using curl (Command Line)

```bash
# Test with specific origin
curl -v \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: GET" \
  http://localhost:8000/users

# Response headers will show:
# access-control-allow-origin: http://localhost:3000
```

### CORS Error Debugging

If you see a CORS error in browser console:

1. **Check origin format** - Must include protocol (`http://` or `https://`)
2. **Check port number** - Must match exactly if configured
3. **Check CORS_ALLOWED_ORIGINS** - Verify it's set and contains your origin
4. **Check API logs** - Look for "CORS: Origin rejected:" messages
5. **Test with curl** - Isolate if it's CORS or another issue

Example error:
```
Access to XMLHttpRequest at 'http://api.local:8000/users' 
from origin 'http://attacker.com' has been blocked by CORS policy: 
Response to preflight request doesn't pass access control check: 
Missing header 'access-control-allow-origin'
```

**Solution:** Add origin to `CORS_ALLOWED_ORIGINS` in `.env`

## Subdomain Wildcard Examples

### Configuration
```bash
CORS_ALLOWED_ORIGINS=https://*.example.com
```

### Matches
- ✅ `https://app.example.com`
- ✅ `https://api.example.com`
- ✅ `https://admin.example.com`
- ✅ `https://a.b.example.com` (nested subdomains)

### Does NOT Match
- ❌ `https://example.com` (no subdomain)
- ❌ `https://exampleX.com` (different domain)
- ❌ `http://app.example.com` (different protocol)

## Related Security Features

- **SQL Injection Prevention** - See `docs/SQL_INJECTION_FIX.md`
- **Authorization** - Task 1.6: Row-level security
- **Rate Limiting** - Task 1.8: Improved protection
- **Authentication** - JWT with short expiration (Task 1.2)

## Troubleshooting

### Error: "CORS_ALLOWED_ORIGINS environment variable is not configured"

**Solution:** Add `CORS_ALLOWED_ORIGINS` to your `.env` file

### Requests work locally but fail in production

**Common issue:** Different protocol or port
```bash
# Local (HTTP without port)
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Production (HTTPS required)
CORS_ALLOWED_ORIGINS=https://app.example.com
```

### Subdomain wildcard not working

**Check format:**
```bash
# Correct
CORS_ALLOWED_ORIGINS=https://*.example.com

# Wrong (no asterisk)
CORS_ALLOWED_ORIGINS=https://example.com

# Wrong (asterisk in wrong place)
CORS_ALLOWED_ORIGINS=https://example.*
```

### Getting CORS errors from same origin

**Most likely:** Including port number in configuration
```bash
# If API is on http://localhost:8000
# Frontend on http://localhost:3000
# Configure:
CORS_ALLOWED_ORIGINS=http://localhost:3000

# NOT:
CORS_ALLOWED_ORIGINS=http://localhost:3000:3000  # Wrong - port already specified
```

## References

- [MDN: CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [OWASP: CORS](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Origin_Resource_Sharing_Cheat_Sheet.html)
- [RFC 7231: Origin Header](https://tools.ietf.org/html/rfc7231#section-6.4)
