# API Versioning Strategy

## Overview

SPLK2 API supports multiple API versions to enable backward compatibility while allowing the API to evolve. Clients can specify which version of the API they want to use.

## Supported Versions

### Current Versions
- **v1** - Current API version (active)

### Planned Versions
- **v2** - Planned for future (coming soon)
- **v3** - Long-term vision (TBD)

## URL Structure

All API endpoints support the versioned URL format:

```
/api/{version}/{resource}/{id}
```

### Examples

```
GET /api/v1/users                    # List all users (v1)
GET /api/v1/users/123                # Get user with ID 123 (v1)
POST /api/v2/users                   # Create user (v2)
PUT /api/v2/users/123                # Update user (v2)
DELETE /api/v2/users/123             # Delete user (v2)
```

## Legacy Format (Backwards Compatibility)

The API still supports the legacy format without the `/api` prefix for backwards compatibility. These requests default to **v1**:

```
GET /users                           # Equivalent to GET /api/v1/users
GET /users/123                       # Equivalent to GET /api/v1/users/123
```

## Version Information Endpoint

Query `/api/versions` to get information about supported API versions:

```bash
curl http://localhost:8000/api/versions
```

Response:
```json
{
  "status": "success",
  "data": {
    "default_version": "v1",
    "supported_versions": [
      {
        "version": "v1",
        "status": "active",
        "deprecation": null
      },
      {
        "version": "v2",
        "status": "active",
        "deprecation": null
      }
    ],
    "documentation_url": "/docs"
  }
}
```

## Response Headers

Every API response includes the `X-API-Version` header indicating which version was used:

```
X-API-Version: v1
X-API-Version: v2
```

## Version Differences

### v1 (Current)
- Full CRUD operations
- Standard response format
- User authentication with JWT
- Role-based access control
- Rate limiting
- File upload support
- Password reset functionality
- Audit logging

### v2 (Future)
Planned enhancements for a future major version:
- Enhanced filtering and sorting
- Field selection support
- Request correlation IDs
- Webhook signature validation
- Real-time updates (WebSocket/SSE)
- Bulk operations

## Migration Guide

### From Legacy to Versioned

**Before (legacy format):**
```bash
curl http://localhost:8000/users
```

**After (versioned format):**
```bash
curl http://localhost:8000/api/v1/users
```

Both formats work, but we recommend using the versioned format for future clarity and compatibility.

## Deprecation Policy

When a version is deprecated:

1. A deprecation notice will be added to the API response
2. The `/versions` endpoint will mark it as "deprecated"
3. Deprecation notice will include sunset date
4. Clients have at least 6 months before a version is removed

### Deprecation Notice Example

```json
{
  "deprecation": {
    "deprecated_at": "2026-06-30",
    "sunset_date": "2027-06-30",
    "message": "v1 is deprecated. Please migrate to v2 by 2027-06-30."
  }
}
```

## Best Practices

### 1. Use Versioned URLs
Always use the `/api/v{version}/` prefix in your requests for clarity:
```bash
curl /api/v1/users  # ✓ Good
curl /users         # ⚠ Works but legacy
```

### 2. Check Version Compatibility
When upgrading clients, check the `X-API-Version` response header:
```bash
curl -i /api/v1/users | grep X-API-Version
```

### 3. Monitor Deprecations
Regularly check the `/versions` endpoint to stay informed about upcoming changes:
```bash
curl /api/versions | jq '.data.supported_versions'
```

### 4. Use Latest Stable Version
Unless you have specific requirements, use the latest stable version:
```bash
curl /api/v2/users  # Use latest stable
```

## Error Handling

### Unsupported Version
```bash
curl /api/v99/users
```

Response (400 Bad Request):
```json
{
  "status": 400,
  "message": "Routing failed",
  "error": "Unsupported API version: v99"
}
```

### Invalid Endpoint
```bash
curl /api/v1/invalid-table
```

Response (400 Bad Request):
```json
{
  "status": 400,
  "message": "Routing failed",
  "error": "Invalid endpoint"
}
```

## Adding a New Version

To add a new API version (e.g., v3):

1. **Update ApiRouter.php**
   ```php
   private const SUPPORTED_VERSIONS = ['v1', 'v2', 'v3'];
   ```

2. **Implement version-specific logic** (if needed)
   ```php
   if ($version === 'v3') {
       // v3-specific implementation
   }
   ```

3. **Update tests**
   - Add tests for the new version
   - Update integration tests

4. **Document changes**
   - Add section to VERSIONING.md
   - Update API documentation

5. **Create deprecation plan**
   - If retiring old versions, set deprecation dates
   - Notify clients 6+ months before removal

## Support Timeline

| Version | Released | Status | Sunset Date |
|---------|----------|--------|-------------|
| v1 | 2026-01 | Active | TBD |
| v2 | TBD | Planned | N/A |
| v3 | TBD | Future | N/A |

## Questions?

For versioning-related questions or concerns:
- Check the `/docs` endpoint for full API documentation
- Review this VERSIONING.md guide
- See NEXT_STEPS.md for planned enhancements
