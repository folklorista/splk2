# Webhook System

## Overview

The webhook system enables real-time notifications to external URLs when events occur in the SPLK2 API. This allows third-party systems to react to changes in near real-time without polling.

## Use Cases

- Send notifications to Slack/Teams when users are created
- Trigger CI/CD pipelines when inventory items are updated
- Update external databases when records are deleted
- Log all changes to an external audit system
- Integrate with CRM systems for customer data synchronization

## Supported Events

Webhooks can be subscribed to the following events:

| Event | Triggered On |
|-------|--------------|
| `user.created` | New user registered |
| `user.updated` | User profile updated |
| `user.deleted` | User deleted |
| `item.created` | New inventory item created |
| `item.updated` | Inventory item modified |
| `item.deleted` | Inventory item deleted |
| `category.created` | New category created |
| `category.updated` | Category modified |
| `category.deleted` | Category deleted |
| `group.created` | New group created |
| `group.updated` | Group modified |
| `group.deleted` | Group deleted |
| `person.created` | New person created |
| `person.updated` | Person modified |
| `person.deleted` | Person deleted |
| `loan.created` | New loan created |
| `loan.updated` | Loan modified |
| `loan.deleted` | Loan deleted |
| `place.created` | New place created |
| `place.updated` | Place modified |
| `place.deleted` | Place deleted |
| `event.created` | New event created |
| `event.updated` | Event modified |
| `event.deleted` | Event deleted |
| `*` | All events |

## Management Endpoints

### Create Webhook
```http
POST /webhooks
Authorization: Bearer <token>
Content-Type: application/json

{
  "url": "https://example.com/webhooks/inventory-changes",
  "events": ["item.created", "item.updated", "item.deleted"],
  "active": true,
  "retry_count": 3,
  "timeout_seconds": 30
}
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Webhook created successfully",
  "data": {
    "webhook_id": 1
  }
}
```

**Restrictions**: Admin only

### List Webhooks
```http
GET /webhooks
Authorization: Bearer <token>
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Webhooks retrieved",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "url": "https://example.com/webhooks/inventory-changes",
      "events": ["item.created", "item.updated", "item.deleted"],
      "active": true,
      "retry_count": 3,
      "timeout_seconds": 30,
      "created_at": "2026-06-29T12:00:00+00:00",
      "updated_at": null
    }
  ]
}
```

**Restrictions**: Admin only

### Get Single Webhook
```http
GET /webhooks/{id}
Authorization: Bearer <token>
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Webhook retrieved",
  "data": {
    "id": 1,
    "url": "https://example.com/webhooks/inventory-changes",
    "events": ["item.created", "item.updated"],
    "active": true,
    "retry_count": 3,
    "timeout_seconds": 30
  }
}
```

**Restrictions**: Admin only

### Update Webhook
```http
PUT /webhooks/{id}
Authorization: Bearer <token>
Content-Type: application/json

{
  "active": false,
  "retry_count": 5,
  "timeout_seconds": 60
}
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Webhook updated successfully"
}
```

**Restrictions**: Admin only

### Delete Webhook
```http
DELETE /webhooks/{id}
Authorization: Bearer <token>
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Webhook deleted successfully"
}
```

**Restrictions**: Admin only

### Test Webhook
```http
POST /webhooks/{id}/test
Authorization: Bearer <token>
```

Sends a test payload to verify the webhook URL is reachable and configured correctly.

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Webhook test successful",
  "data": {
    "status_code": 200,
    "response": "OK",
    "success": true
  }
}
```

**Restrictions**: Admin only

## Webhook Payload

When a webhook event is triggered, the following payload is sent via POST:

```json
{
  "event": "item.created",
  "timestamp": "2026-06-29T12:00:00+00:00",
  "data": {
    "table": "items",
    "record_id": 42,
    "data": {
      "category_id": 5,
      "inventory_number": "INV-2024-001",
      "status": "active"
    }
  }
}
```

### Payload Fields

| Field | Type | Description |
|-------|------|-------------|
| `event` | string | Event name (e.g., "item.created") |
| `timestamp` | string | ISO 8601 timestamp when event occurred |
| `data.table` | string | Table name (items, users, etc.) |
| `data.record_id` | integer | ID of the affected record |
| `data.data` | object | For CREATE: new data; for UPDATE: changed fields; for DELETE: old data |
| `data.old_values` | object | (UPDATE/DELETE only) Previous values |
| `data.new_values` | object | (UPDATE only) New values |

## Headers

Webhook requests include the following HTTP headers:

```
Content-Type: application/json
User-Agent: SPLK2-Webhook/1.0
X-Webhook-Test: true (only for test requests)
```

## Delivery

### Retry Logic

- **Retries**: Configurable per webhook (default 3)
- **Backoff**: Exponential backoff (2^n seconds, max 32 seconds)
- **On retry**: 5xx errors and network timeouts trigger retries
- **No retry**: 4xx errors (client errors) do not trigger retries

### Success Criteria

A webhook delivery is considered successful if the target server responds with HTTP status 2xx (200-299).

### Timeout

Configurable per webhook, default 30 seconds. If the target server doesn't respond within this time, it's treated as a network error and triggers retry logic.

## Configuration

### Default Settings
- **retry_count**: 3 (can be set to 1-10)
- **timeout_seconds**: 30 (can be set to 5-300)

### Example: High-Reliability Webhook
```json
{
  "url": "https://critical-system.com/webhooks",
  "events": ["user.created", "user.deleted"],
  "retry_count": 10,
  "timeout_seconds": 60,
  "active": true
}
```

### Example: Quick Webhook
```json
{
  "url": "https://analytics.example.com/events",
  "events": ["*"],
  "retry_count": 1,
  "timeout_seconds": 5,
  "active": true
}
```

## Audit Trail

All webhook deliveries are logged in the `webhook_logs` table with:
- Webhook ID
- Event name
- Payload sent
- HTTP response status code
- Response body
- Delivery timestamp
- Number of delivery attempts

This allows debugging failed deliveries and tracking webhook history.

## Security

### Authentication

Webhooks are managed by admins only. Regular users cannot create, modify, or delete webhooks.

### Target URLs

- Must be valid HTTPS or HTTP URLs
- Recommended: Use HTTPS for production
- No IP whitelist enforcement (customer's responsibility)

### Request Validation

When receiving webhooks in your application, validate:
1. Request comes from expected IP/domain
2. Payload structure is valid JSON
3. Event name is in your supported list
4. Respond with 2xx status code for success

## Implementation Examples

### Slack Integration
```bash
# Create webhook that posts to Slack
curl -X POST http://localhost:8000/webhooks \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://hooks.slack.com/services/YOUR/WEBHOOK/URL",
    "events": ["user.created"],
    "active": true
  }'
```

### External Audit Log
```bash
# Create webhook for all events to audit system
curl -X POST http://localhost:8000/webhooks \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://audit-system.example.com/api/events",
    "events": ["*"],
    "retry_count": 10,
    "active": true
  }'
```

### Test Webhook
```bash
# Test webhook before enabling
curl -X POST http://localhost:8000/webhooks/1/test \
  -H "Authorization: Bearer <token>"
```

## Error Handling

| Status | Meaning |
|--------|---------|
| 200 | Webhook created/updated/retrieved/deleted successfully |
| 400 | Invalid URL, missing events, or invalid event name |
| 403 | Only administrators can manage webhooks |
| 404 | Webhook not found |
| 500 | Database or delivery error |

## Limitations

- Maximum 2048 characters for webhook URL
- Maximum 25 events per webhook (or wildcard)
- Webhook payloads may be retried multiple times - ensure idempotency
- No signature/HMAC verification (on roadmap)
- No webhook secret/authentication (on roadmap)

## Roadmap

Planned enhancements:
- [ ] HMAC signature verification for webhook requests
- [ ] Webhook secret tokens for authentication
- [ ] Webhook response handlers (success/failure callbacks)
- [ ] Rate limiting per webhook
- [ ] Webhook pause/resume without deletion
- [ ] Message queue for async delivery (instead of synchronous retries)
- [ ] Webhook analytics dashboard
