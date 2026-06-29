# SPLK2 REST API - Complete Reference

## Overview

SPLK2 is a **generic, table-agnostic REST API** for managing MySQL data with authentication, validation, and business rules.

### Key Capabilities

- **Dynamic CRUD**: Create, read, update, delete on any table without endpoint configuration
- **Smart Search**: Full-text search with FULLTEXT index support, column-specific filtering
- **Pagination & Sorting**: Offset/limit pagination with custom sort order
- **Table Rules**: Configurable validation, constraints, and business logic per table
- **Hierarchical Data**: Native support for tree structures (categories, groups)
- **Audit Logging**: All operations tracked with user, timestamp, changes
- **Schema Introspection**: Dynamically discover table structure, types, constraints
- **JWT Authentication**: Stateless token-based auth, 365-day expiry

### Base URL

```
http://localhost:8000
```

### Authentication

All endpoints except `/login` and `/register` require JWT Bearer token:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Get token from `/login` endpoint.

---

## Authentication Endpoints

### POST /login

Authenticate user and receive JWT token.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123"
}
```

**Response (200):**
```json
{
  "status": 200,
  "message": "User logged in",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJsb2NhbGhvc3QiLCJzdWIiOjEsImlhdCI6MTczNDI3NDA5NiwiZXhwIjoyMzM0Mjc0MDk2LCJ1c2VyIjp7ImlkIjoxLCJmaXJzdE5hbWUiOiJKb2huIiwibGFzdE5hbWUiOiJEb2UifX0..."
  },
  "error": null
}
```

**Error (401):**
```json
{
  "status": 401,
  "message": "Invalid credentials",
  "data": null,
  "error": "Unauthorized access"
}
```

**Notes:**
- Token is valid for 365 days from issue
- Token payload includes user ID, name for audit logging
- User must exist in `users` table with correct password

---

### POST /register

Register a new user account.

**Request:**
```json
{
  "email": "newuser@example.com",
  "password": "securePassword123",
  "first_name": "John",
  "last_name": "Doe"
}
```

**Validation Rules:**
- `email`: Must be valid email format, must be unique
- `password`: Minimum 8 characters
- `first_name`, `last_name`: 2-64 characters each

**Response (201):**
```json
{
  "status": 201,
  "message": "User registered",
  "data": {
    "id": 42
  },
  "error": null
}
```

**Error (400):**
```json
{
  "status": 400,
  "message": "Validation failed",
  "data": null,
  "error": "Email already exists"
}
```

---

## CRUD Endpoints

All endpoints follow `/{{ tableName }}` pattern. Works with any MySQL table (users, items, categories, etc.)

### GET /{{ tableName }}

Retrieve multiple records with pagination, sorting, and search.

**Query Parameters (via HTTP Headers):**

| Header | Type | Example | Description |
|--------|------|---------|-------------|
| `X-Pagination-Limit` | integer | 10 | Records per page |
| `X-Pagination-Offset` | integer | 0 | Records to skip |
| `X-Sort-By` | string | created_at | Column to sort by |
| `X-Sort-Direction` | string | DESC | ASC or DESC |
| `X-Search-Query` | string | john | Search text (URL-encoded) |
| `X-Search-Columns` | string | first_name,last_name | Comma-separated columns to search |

**Request Example (cURL):**
```bash
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Pagination-Limit: 10" \
  -H "X-Pagination-Offset: 0" \
  -H "X-Sort-By: created_at" \
  -H "X-Sort-Direction: DESC" \
  -H "X-Search-Query: john" \
  -H "X-Search-Columns: first_name,last_name"
```

**Request Example (JavaScript):**
```javascript
const headers = new Headers({
  'Authorization': `Bearer ${token}`,
  'X-Pagination-Limit': '10',
  'X-Pagination-Offset': '0',
  'X-Sort-By': 'created_at',
  'X-Sort-Direction': 'DESC',
  'X-Search-Query': 'john',
  'X-Search-Columns': 'first_name,last_name'
});

const response = await fetch('http://localhost:8000/users', { headers });
const data = await response.json();
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Records found",
  "data": [
    {
      "id": 1,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": null
    }
  ],
  "error": null,
  "meta": {
    "pagination": {
      "limit": 10,
      "offset": 0,
      "total_records": 150,
      "total_pages": 15
    },
    "sorting": {
      "order_by": "created_at",
      "direction": "DESC"
    },
    "search": {
      "query": "john",
      "columns": ["first_name", "last_name"],
      "fulltext": false
    }
  }
}
```

**Response (204) - No Records:**
```json
{
  "status": 204,
  "message": "No records found",
  "data": null,
  "error": null
}
```

**Behavior:**
- If no pagination limit specified, returns all records
- Search uses FULLTEXT if index exists, otherwise LIKE
- Columns (password) are automatically excluded
- Pagination metadata only included if limit specified

---

### GET /{{ tableName }}/{{ id }}

Retrieve a single record by ID.

**Request:**
```bash
curl -X GET "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Record found",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": null
  },
  "error": null
}
```

**Response (404):**
```json
{
  "status": 404,
  "message": "Record not found",
  "data": null,
  "error": "Not found"
}
```

---

### POST /{{ tableName }}

Create a new record.

**Request:**
```bash
curl -X POST "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@example.com",
    "password": "securePassword123",
    "first_name": "Jane",
    "last_name": "Doe"
  }'
```

**Validation:**
- Applied from `table-rules.php` configuration
- Field-level constraints (required, minLength, unique, etc.)
- Table-specific business rules (e.g., "admin cannot be duplicate")

**Response (201):**
```json
{
  "status": 201,
  "message": "Record created",
  "data": {
    "id": 42
  },
  "error": null
}
```

**Response (400) - Validation Error:**
```json
{
  "status": 400,
  "message": "Validation failed",
  "data": null,
  "error": "email already exists; password must be at least 8 characters"
}
```

**Response (403) - Business Rule Violation:**
```json
{
  "status": 403,
  "message": "Business rule violation",
  "data": null,
  "error": "Admin accounts must have unique email"
}
```

**Behavior:**
- System columns (id, created_at, updated_at) ignored if provided
- Password fields automatically hashed (BCRYPT)
- Boolean fields converted (true → 1, false → 0)
- Audit log created automatically with user ID

---

### PUT /{{ tableName }}/{{ id }}

Update an existing record.

**Request:**
```bash
curl -X PUT "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Jane",
    "last_name": "Smith"
  }'
```

**Notes:**
- Only provided fields are updated (partial update)
- System columns protected (id, created_at, updated_at cannot be changed)
- Validation applied same as POST
- `updated_at` automatically set to current timestamp

**Response (200):**
```json
{
  "status": 200,
  "message": "Record updated",
  "data": null,
  "error": null
}
```

**Response (400) - No Changes:**
```json
{
  "status": 400,
  "message": "No change detected",
  "data": null,
  "error": "Invalid input"
}
```

**Response (403) - Business Rule Violation:**
```json
{
  "status": 403,
  "message": "Business rule violation",
  "data": null,
  "error": "Admin cannot update their own role"
}
```

---

### DELETE /{{ tableName }}/{{ id }}

Delete a record by ID.

**Request:**
```bash
curl -X DELETE "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Record deleted",
  "data": null,
  "error": null
}
```

**Response (403) - Business Rule Violation:**
```json
{
  "status": 403,
  "message": "Business rule violation",
  "data": null,
  "error": "Cannot delete the last admin"
}
```

**Response (404):**
```json
{
  "status": 404,
  "message": "Record not found",
  "data": null,
  "error": "Not found"
}
```

**Behavior:**
- Foreign key constraints enforced
- CASCADE deletes apply based on DB schema
- Audit log created automatically
- Business rules (hooks) executed before/after delete

---

## Schema & Introspection

### GET /schema/{{ tableName }}

Retrieve table structure, columns, types, and relationships.

**Request:**
```bash
curl -X GET "http://localhost:8000/schema/users" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (200):**
```json
{
  "name": ["users"],
  "comment": ["uživatelé"],
  "columns": [
    {
      "name": "id",
      "type": "int",
      "nullable": false,
      "default": null,
      "comment": null
    },
    {
      "name": "email",
      "type": "varchar(256)",
      "nullable": false,
      "default": null,
      "comment": "e-mail"
    },
    {
      "name": "password",
      "type": "varchar(256)",
      "nullable": false,
      "default": null,
      "comment": "přihlašovací heslo"
    },
    {
      "name": "first_name",
      "type": "varchar(64)",
      "nullable": false,
      "default": null,
      "comment": "křestní jméno"
    }
  ],
  "foreign_keys": [
    {
      "column": "role_id",
      "referenced_table": "roles",
      "referenced_column": "id"
    }
  ]
}
```

**Use Cases:**
- Dynamic form generation (frontend doesn't need to hardcode fields)
- API schema discovery
- Data validation (frontend can validate before POST/PUT)
- Building query builders

---

## Search

### GET /{{ tableName }}/search?search={{ query }}

Legacy search endpoint. Use main GET with headers for more control.

**Request:**
```bash
curl -X GET "http://localhost:8000/users/search?search=john" \
  -H "Authorization: Bearer $TOKEN"
```

**Response:**
```json
{
  "status": 200,
  "message": "Records found",
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com"
    }
  ],
  "error": null
}
```

**Note:** Prefer using GET /users with X-Search-Query header for more control.

---

## Foreign Keys & References

### GET /{{ tableName }}/options

Get available options for a referenced table (for dropdowns/selects).

**Request:**
```bash
curl -X GET "http://localhost:8000/categories/options" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (200):**
```json
[
  {
    "id": 1,
    "name": "Electronics"
  },
  {
    "id": 2,
    "name": "Electronics > Computers"
  }
]
```

**Behavior:**
- Returns id + name for each option
- For hierarchical tables (with parent_id), returns full path
- Useful for building select dropdowns
- Automatically detects "name" column or FULLTEXT index

### GET /{{ tableName }}?foreignKeys=true&table={{ refTable }}&{{ column }}={{ value }}

Get records filtered by foreign key.

**Request:**
```bash
curl -X GET "http://localhost:8000/items?foreignKeys=true&table=items&category_id=5" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Records found",
  "data": [
    {
      "id": 1,
      "category_id": 5,
      "inventory_number": "COMP-001",
      "status": "active"
    }
  ],
  "error": null
}
```

---

## Tree Endpoints (Hierarchical Data)

### GET /categories

Retrieve full category hierarchy as nested tree.

**Request:**
```bash
curl -X GET "http://localhost:8000/categories" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Records found",
  "data": [
    {
      "id": "1",
      "name": "Electronics",
      "children": [
        {
          "id": "3",
          "name": "Computers",
          "children": [
            {
              "id": "4",
              "name": "Laptops",
              "children": []
            }
          ]
        }
      ]
    }
  ],
  "error": null
}
```

**Use Cases:**
- Tree view components
- Nested dropdowns
- Hierarchical navigation

### PUT /categories

Update entire category tree structure.

**Request:**
```bash
curl -X PUT "http://localhost:8000/categories" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '[
    {
      "id": "1",
      "name": "Electronics",
      "children": [
        {
          "id": "3",
          "name": "Computers",
          "children": []
        }
      ]
    }
  ]'
```

**Behavior:**
- Atomic operation: all changes succeed or all fail
- Handles create/update/delete in single request
- Maintains parent-child relationships
- Updates position/order automatically
- Audit log records tree structure change

### GET /groups, PUT /groups

Same as categories but for groups table.

---

## Error Handling

### Standard Error Responses

All errors follow consistent format:

```json
{
  "status": 400,
  "message": "Human-readable message",
  "data": null,
  "error": "Specific error reason"
}
```

### Common Status Codes

| Code | Meaning | Retry? |
|------|---------|--------|
| 200 | Success | No |
| 201 | Created | No |
| 204 | No content | No |
| 400 | Bad request (validation, invalid input) | No |
| 401 | Unauthorized (missing/invalid token) | Yes (get new token) |
| 403 | Forbidden (business rule violation) | No |
| 404 | Not found | No |
| 500 | Server error | Yes (exponential backoff) |

### Specific Error Scenarios

**Invalid Token:**
```json
{
  "status": 401,
  "message": "Unauthorized access",
  "data": null,
  "error": "You must be logged in to access this resource"
}
```

**Validation Failure:**
```json
{
  "status": 400,
  "message": "Validation failed",
  "data": null,
  "error": "email must be valid email; password must be at least 8 characters"
}
```

**Business Rule Violation:**
```json
{
  "status": 403,
  "message": "Business rule violation",
  "data": null,
  "error": "Cannot delete the last admin"
}
```

**Foreign Key Constraint:**
```json
{
  "status": 400,
  "message": "Record not deleted",
  "data": null,
  "error": "Cannot delete category with items"
}
```

---

## Table-Specific Rules

Validation and business logic is configured in `/api/config/table-rules.php`.

### Example: Users Table

```php
'users' => [
    'validation' => [
        'email' => ['type' => 'email', 'required' => true, 'unique' => true],
        'password' => ['minLength' => 8, 'required' => true],
        'first_name' => ['minLength' => 2, 'maxLength' => 64, 'required' => true],
    ],
    'hooks' => [
        'beforeDelete' => function($id, $user, $logger, $db) {
            // Admin cannot delete themselves
            if ($user->id === $id) {
                throw new \Exception('You cannot delete your own account', 403);
            }
        }
    ]
]
```

### Validation Constraints

- `required`: Field must be present
- `type`: "email", "integer", "string", etc.
- `minLength`, `maxLength`: String length constraints
- `enum`: Predefined allowed values
- `unique`: Value must be unique in table
- `unique_with`: Unique combination (e.g., category_id + inventory_number)

### Business Logic Hooks

- `beforeCreate`: Run before INSERT (validate, check permissions)
- `afterCreate`: Run after INSERT (side effects, send notifications)
- `beforeUpdate`: Run before UPDATE
- `afterUpdate`: Run after UPDATE
- `beforeDelete`: Run before DELETE (check constraints, prevent invalid deletes)
- `afterDelete`: Run after DELETE (cleanup, cascade logic)

---

## Audit Logging

Every CRUD operation creates an audit log entry with:
- User ID (who did it)
- Operation type (CREATE, UPDATE, DELETE)
- Table and record ID
- Timestamp
- Changed data (for UPDATE)

**Logged Operations:**
- USER_LOGIN - User authentication
- DATA_INSERT - Record created
- DATA_UPDATE - Record modified
- DATA_DELETE - Record deleted
- TREE_UPDATE - Hierarchy changed

**Usage:**
- Track who changed what when
- Compliance & audit requirements
- Debugging & forensics
- Revert to previous states

---

## Implementation Notes

### Boolean Fields

- Stored as `tinyint(1)` in MySQL
- API converts: 0 ↔ false, 1 ↔ true
- Transparent to client

### Password Handling

- Automatically hashed with BCRYPT on CREATE/UPDATE
- Password column automatically excluded from responses
- No plaintext passwords ever transmitted or stored

### Timestamps

- `created_at`: Set automatically on INSERT, never modified
- `updated_at`: Set automatically on INSERT, updated on each UPDATE
- Format: ISO 8601 (2025-01-15T10:00:00Z)

### Search Behavior

- FULLTEXT index used if available (faster, phrase matching)
- LIKE operator used if no index (slower, substring matching)
- Search is case-insensitive

### Pagination Defaults

- If no limit specified: returns all records
- Recommended: use limit of 10-50 for performance
- Offset must be >= 0, typically: offset = page * limit

### CORS

API includes CORS headers for cross-origin requests from web apps.

---

## Quick Reference: cURL Examples

### Login & Get Token
```bash
curl -X POST "http://localhost:8000/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com", "password":"pass123"}' \
  | jq '.data.token' -r > token.txt

export TOKEN=$(cat token.txt)
```

### List with Pagination & Sort
```bash
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Pagination-Limit: 20" \
  -H "X-Pagination-Offset: 0" \
  -H "X-Sort-By: created_at" \
  -H "X-Sort-Direction: DESC"
```

### Search
```bash
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Search-Query: john" \
  -H "X-Search-Columns: first_name,last_name"
```

### Create
```bash
curl -X POST "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "new@example.com",
    "password": "password123",
    "first_name": "John",
    "last_name": "Doe"
  }'
```

### Update
```bash
curl -X PUT "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name": "Jane"}'
```

### Delete
```bash
curl -X DELETE "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN"
```

### Get Schema
```bash
curl -X GET "http://localhost:8000/schema/users" \
  -H "Authorization: Bearer $TOKEN" \
  | jq '.'
```

---

## Related Documentation

- **OpenAPI Specification**: `/api/openapi.yaml` - Complete API schema (Swagger/API doc format)
- **Implementation Guide**: `/api/REUSABLE.md` - How to use API in other projects
- **Examples**: `/api/docs/examples/` - Practical code samples
- **Test Suite**: `/api/tests/` - Unit and integration tests

