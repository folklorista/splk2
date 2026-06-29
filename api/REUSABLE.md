# SPLK2 API - Reusability Guide

## Using SPLK2 API in Your Project

This guide explains how to extract and use the SPLK2 REST API in a different project.

## What You Get

A **production-ready, generic REST API** for any MySQL database with:

- Dynamic CRUD endpoints (no code changes needed for new tables)
- JWT authentication
- Input validation & business rules (configurable per table)
- Full-text search, pagination, sorting
- Audit logging
- Schema introspection
- Support for hierarchical data (trees)

## Quick Start (30 minutes)

### Step 1: Copy API Files

```bash
# In your new project directory
mkdir api
cp -r /path/to/splk2/api/* ./api/

# Install dependencies
cd api
composer install
```

### Step 2: Configure Database

Edit `api/config/config.local.php`:

```php
return [
    'database' => [
        'host' => 'localhost',          // Your MySQL host
        'username' => 'myapp',           // Your DB user
        'password' => 'mySecurePass',    // Your DB password
        'dbname' => 'my_app_db',         // Your DB name
    ],
    'auth' => [
        'jwt_secret' => 'generate-a-strong-random-string-here',
    ],
    'pathIndex' => [
        'table' => 0,
        'id' => 1,
    ],
    'log' => [
        'path' => __DIR__ . '/../../log/api/app.log',
        'level' => LogLevel::DEBUG,
    ],
];
```

Generate JWT secret:
```bash
php -r 'echo bin2hex(random_bytes(32));'
```

### Step 3: Create Database Schema

Your database must already exist. The API works with any MySQL schema.

**Minimal example:**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(256) UNIQUE NOT NULL,
    password VARCHAR(256) NOT NULL,
    first_name VARCHAR(64) NOT NULL,
    last_name VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    parent_id INT,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);
```

**Key requirements:**
- Tables need `id` primary key
- Boolean fields: use `tinyint(1)`
- Timestamps: use `created_at`, `updated_at` (auto-managed)
- Foreign keys: use `FOREIGN KEY` constraints
- Tree structures: add `parent_id` and `position` columns

### Step 4: Configure Table Rules (Optional but Recommended)

Edit `api/config/table-rules.php` to add validation and business logic:

```php
return [
    'users' => [
        'validation' => [
            'email' => [
                'type' => 'email',
                'required' => true,
                'unique' => true,
            ],
            'password' => [
                'minLength' => 8,
                'required' => true,
            ],
        ],
        'hooks' => [
            'beforeDelete' => function($id, $user, $logger, $db) {
                if ($user->id === $id) {
                    throw new \App\RuleException(
                        'You cannot delete your own account',
                        403,
                        'users',
                        'beforeDelete'
                    );
                }
            }
        ]
    ],
    
    'items' => [
        'validation' => [
            'name' => ['required' => true, 'minLength' => 2],
            'category_id' => ['type' => 'integer', 'required' => true],
        ]
    ]
];
```

### Step 5: Start API Server

```bash
cd api
php -S localhost:8000 -t public
```

The API is now running at `http://localhost:8000`

### Step 6: Test It

**Login:**
```bash
curl -X POST "http://localhost:8000/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

**Get Records:**
```bash
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "X-Pagination-Limit: 10"
```

## Configuration Deep Dive

### Database Configuration

`api/config/config.local.php`:

```php
'database' => [
    'host' => 'localhost',       // MySQL server
    'username' => 'user',        // DB user
    'password' => 'pass',        // DB password
    'dbname' => 'my_app_db',     // Database name
],
```

Connection issues? Check:
- MySQL is running: `mysql -u user -p dbname`
- Credentials are correct
- User has permissions: `GRANT ALL ON my_app_db.* TO 'user'@'localhost';`

### Table Rules Configuration

`api/config/table-rules.php` defines:

1. **Validation Constraints**

```php
'field_name' => [
    'required' => true,              // Field must be present
    'type' => 'email',               // Type checking (email, integer, string, etc.)
    'minLength' => 2,                // Minimum string length
    'maxLength' => 64,               // Maximum string length
    'min' => 0,                      // Minimum numeric value
    'max' => 100,                    // Maximum numeric value
    'enum' => ['active', 'inactive'], // Allowed values
    'unique' => true,                // Value must be unique in table
]
```

2. **Business Logic Hooks**

```php
'hooks' => [
    'beforeCreate' => function($data, $user, $logger) {
        // Run before INSERT
        // Throw exception to prevent creation
    },
    'afterCreate' => function($id, $user, $logger, $db) {
        // Run after successful INSERT
        // Side effects, notifications, etc.
    },
    'beforeUpdate' => function($id, $data, $user, $logger, $db) {
        // Run before UPDATE
    },
    'afterUpdate' => function($id, $user, $logger, $db) {
        // Run after successful UPDATE
    },
    'beforeDelete' => function($id, $user, $logger, $db) {
        // Run before DELETE
        // Throw exception to prevent deletion
    },
    'afterDelete' => function($id, $user, $logger, $db) {
        // Run after successful DELETE
        // Cleanup, cascade logic
    },
]
```

### Logging Configuration

`api/config/config.local.php`:

```php
'log' => [
    'path' => __DIR__ . '/../../log/api/app.log',  // Where to write logs
    'level' => LogLevel::DEBUG,                     // DEBUG, INFO, WARNING, ERROR
],
```

Create log directory:
```bash
mkdir -p log/api
chmod 755 log/api
```

## API Endpoints

Full documentation: See `docs/API.md` or `openapi.yaml`

### Quick Reference

```
POST   /login              - Authenticate & get token
POST   /register           - Register new user

GET    /{table}            - List all records (with pagination, search, sort)
GET    /{table}/{id}       - Get single record
POST   /{table}            - Create record
PUT    /{table}/{id}       - Update record
DELETE /{table}/{id}       - Delete record

GET    /schema/{table}     - Get table structure
GET    /{table}/options    - Get foreign key options
GET    /categories         - Get hierarchy tree
PUT    /categories         - Save hierarchy tree
```

All endpoints require JWT Bearer token except `/login` and `/register`.

## Customization

### Adding New Tables

1. Create the table in MySQL with proper structure
2. (Optional) Add validation/rules to `table-rules.php`
3. That's it! The API auto-discovers the schema

### Modifying Validation

Edit `api/config/table-rules.php`:

```php
'items' => [
    'validation' => [
        'name' => [
            'required' => true,
            'minLength' => 3,
            'maxLength' => 100,
        ]
    ]
]
```

### Adding Custom Logic

Use hooks in `table-rules.php`:

```php
'products' => [
    'hooks' => [
        'afterCreate' => function($id, $user, $logger, $db) {
            // Send notification when product created
            // Update related records
            // Call external API
        }
    ]
]
```

### Changing Response Format

Modify `api/src/Response.php` class (if needed).

## Integration with Frontend

### Angular/TypeScript

```typescript
// Create service
import { HttpClient, HttpHeaders } from '@angular/common/http';

@Injectable()
export class ApiService {
  private apiUrl = 'http://localhost:8000';
  private token: string;

  constructor(private http: HttpClient) {
    this.token = localStorage.getItem('authToken');
  }

  getRecords(table: string, limit: number = 10, offset: number = 0) {
    const headers = new HttpHeaders({
      'Authorization': `Bearer ${this.token}`,
      'X-Pagination-Limit': limit.toString(),
      'X-Pagination-Offset': offset.toString(),
    });
    return this.http.get(`${this.apiUrl}/${table}`, { headers });
  }

  createRecord(table: string, data: any) {
    const headers = new HttpHeaders({
      'Authorization': `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    });
    return this.http.post(`${this.apiUrl}/${table}`, data, { headers });
  }
}
```

### React

```javascript
const API_URL = 'http://localhost:8000';

async function getRecords(table, limit = 10, offset = 0) {
  const token = localStorage.getItem('authToken');
  const response = await fetch(`${API_URL}/${table}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'X-Pagination-Limit': limit.toString(),
      'X-Pagination-Offset': offset.toString(),
    }
  });
  return response.json();
}

async function createRecord(table, data) {
  const token = localStorage.getItem('authToken');
  const response = await fetch(`${API_URL}/${table}`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data)
  });
  return response.json();
}
```

### JavaScript (Vanilla)

```javascript
// Login
const loginRes = await fetch('http://localhost:8000/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'user@example.com', password: 'pass' })
});
const { data } = await loginRes.json();
localStorage.setItem('authToken', data.token);

// Fetch with token
const token = localStorage.getItem('authToken');
const res = await fetch('http://localhost:8000/users', {
  headers: { 'Authorization': `Bearer ${token}` }
});
const json = await res.json();
console.log(json.data);  // Array of records
```

### Mobile (React Native / Flutter)

Same pattern - send Authorization header with Bearer token.

## Troubleshooting

### "Table not found" Error

- Check table exists in database: `SHOW TABLES;`
- Check table name is correct (case-sensitive)
- Verify database connection in config

### "Unauthorized access" Error

- Token missing or invalid
- Token expired (365 day max lifetime)
- Get new token from `/login`

### "Validation failed" Error

- Check field types match schema
- Check required fields are present
- Check string lengths (minLength, maxLength)
- See error message for details

### Performance Issues

- Add pagination: `X-Pagination-Limit: 50`
- Add indexes to frequently searched columns
- Use FULLTEXT indexes for search
- Monitor MySQL with `SHOW PROCESSLIST;`

### CORS Issues

API includes CORS headers by default. If still blocked:

1. Check frontend origin in CORS config
2. Verify API is running on correct port
3. Check request headers in browser DevTools

## Security Best Practices

1. **Always use HTTPS in production**
   - Never send tokens over plain HTTP

2. **Change JWT secret**
   ```bash
   php -r 'echo bin2hex(random_bytes(32));'
   ```
   Put generated value in `config.local.php`

3. **Set strong passwords**
   - Minimum 8 characters enforced by API
   - Passwords hashed with BCRYPT

4. **Database permissions**
   - Create limited user: `GRANT SELECT,INSERT,UPDATE,DELETE ON db.* TO 'api'@'localhost';`

5. **Validate input**
   - Use table rules for validation
   - Database will reject invalid data

6. **Audit logging**
   - All operations logged automatically
   - Check `audit_logs` table for suspicious activity

## Monitoring

### Check Logs

```bash
tail -f api/log/api/app.log
```

### Monitor Database

```bash
# Check large queries
mysql> SET SESSION sql_mode = '';
mysql> SHOW PROCESSLIST;

# Check table sizes
mysql> SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
       FROM information_schema.tables 
       WHERE table_schema = 'my_app_db' 
       ORDER BY (data_length + index_length) DESC;
```

### Health Check Endpoint

Test API is running:
```bash
curl http://localhost:8000/login
```

Should respond with 400 (missing credentials) not connection error.

## Deployment

### Production Checklist

- [ ] Change JWT secret
- [ ] Use environment variables (not config files)
- [ ] Enable HTTPS/TLS
- [ ] Set proper database permissions
- [ ] Configure logging to file
- [ ] Enable MySQL slow query log
- [ ] Set up automated backups
- [ ] Monitor disk space
- [ ] Use reverse proxy (nginx/Apache)
- [ ] Enable caching where possible

### Environment Variables

Create `.env` file:
```bash
APP_ENV=production
DB_HOST=db.production.com
DB_USER=api_user
DB_PASSWORD=secure_password_here
DB_NAME=production_db
JWT_SECRET=your-secret-here
```

Load in PHP:
```php
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

## Getting Help

1. Read full API documentation: `docs/API.md`
2. Check OpenAPI spec: `openapi.yaml`
3. Review test examples: `tests/Integration/`
4. Inspect error messages - they're descriptive

## License

MIT - Use freely in your projects

## Troubleshooting Common Issues

### Issue: "PDOException: SQLSTATE[HY000]: General error: 2006"

**Solution:** MySQL connection lost
- Check MySQL is running
- Check credentials
- Check network connectivity

### Issue: Slow queries

**Solution:** Add indexes
```sql
ALTER TABLE items ADD INDEX idx_category (category_id);
ALTER TABLE users ADD FULLTEXT INDEX ft_name (first_name, last_name);
```

### Issue: Large audit logs

**Solution:** Archive old logs
```sql
DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

