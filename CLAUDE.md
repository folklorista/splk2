# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**SPLK2** is a full-stack web application with:
- **Frontend:** Angular 19 (TypeScript, modern browser features)
- **Backend:** PHP 8.1+ REST API with JWT authentication and dynamic CRUD operations
- **Database:** MySQL with support for hierarchical data and audit logging

The API is **production-ready** with comprehensive security fixes completed (Phase 1 & 2 roadmap), and is actively being enhanced with additional features (Phase 3: bulk operations, sorting, caching).

## Quick Start Commands

### Frontend (Angular)
```bash
cd frontend

# Install dependencies
npm install

# Development server on http://localhost:4200
npm start

# Build for production
npm run build

# Run tests
npm test

# Watch mode (continuous build)
npm run watch
```

### Backend (PHP REST API)
```bash
cd api

# Install dependencies
composer install

# Development server on http://localhost:8000
npm run serve
# OR manually:
php -S localhost:8000 -t public

# Run tests
npm test

# Run test coverage report
npm run test:coverage

# Apply database migrations
npm run migrate

# Check schema consistency
npm run check-schema
```

### Running Both Together

**Terminal 1:**
```bash
cd frontend && npm start
# Starts on http://localhost:4200
```

**Terminal 2:**
```bash
cd api && npm run serve
# Starts on http://localhost:8000
```

The frontend communicates with the backend via REST API calls (configured in `frontend/src/app/config/`).

## Architecture & Structure

### Frontend Architecture

**Directory Structure:**
```
frontend/src/app/
├── components/          # Reusable UI components
├── services/            # HTTP calls to API, business logic
├── models/              # TypeScript interfaces for data types
└── config/              # Configuration (API endpoints, etc.)
```

**Key Technologies:**
- **Angular 19** with latest standalone components
- **RxJS** for reactive data flows
- **Angular Router** for navigation
- **HttpClient** for API communication
- **Karma/Jasmine** for testing

**Build Output:** Builds to `dist/` directory with SSR support via Angular 19 platform-server.

### Backend Architecture

**Directory Structure:**
```
api/
├── src/                 # Core business logic classes
│   ├── Database.php     # MySQL connection & query helpers
│   ├── Auth.php         # JWT token generation & validation
│   ├── Endpoints.php    # CRUD endpoint routing logic
│   ├── RoleBasedAccessControl.php  # Permission checking
│   ├── RateLimiter.php  # Request rate limiting
│   └── ... [other core classes]
├── config/              # Configuration files
│   ├── config.local.php # Database, JWT secret, paths
│   └── table-rules.php  # Validation & hooks per table
├── migrations/          # Database schema version control
├── public/              # Web root (only index.php exposed)
├── tests/               # PHPUnit test suite
└── index.php            # Main API entry point
```

**Key Components:**
- **Routing:** `api/public/index.php` routes all requests to endpoints based on HTTP method and path
- **Database:** Dynamic schema introspection allows auto-generating CRUD for any MySQL table
- **Authentication:** JWT tokens with refresh token rotation, 15-minute expiration
- **Authorization:** Row-level security (users access own data by default), role-based permissions
- **Validation:** Configurable input validation per table via `table-rules.php`
- **Audit Logging:** All CRUD operations logged with user, timestamp, changes
- **API Versioning:** Endpoints under `/api/v1/` namespace
- **Caching:** ETag and Cache-Control headers for client-side caching
- **Search & Sorting:** Full-text search, multi-column sort, field selection via query params

### Database Schema

The API works with any MySQL database. Key conventions:
- Every table must have `id` INT PRIMARY KEY AUTO_INCREMENT
- Tables support `created_at` and `updated_at` TIMESTAMP columns (auto-managed)
- Foreign keys create relationship options automatically (see `/options` endpoint)
- Tree structures: tables with `parent_id` + `position` are treated as hierarchies
- Boolean fields: use `tinyint(1)`

**Key Tables:**
- `users` — Authentication & authorization
- `refresh_tokens` — Token rotation (15-min access token + long-lived refresh token)
- `audit_logs` — All CRUD operations with changes tracked
- `password_reset_tokens` — Password recovery flow
- Application-specific tables configured in `table-rules.php`

## Important Patterns & Conventions

### Authentication & Authorization

**JWT Flow:**
1. User logs in: `POST /login` → receive `access_token` + `refresh_token`
2. Access token expires in 15 minutes (check NEXT_STEPS.md Phase 1.2)
3. Use refresh token: `POST /auth/refresh` → get new access token
4. All protected endpoints require: `Authorization: Bearer <token>` header

**Permission Checking:**
- Defined in `api/src/RoleBasedAccessControl.php`
- Checked for every CRUD operation via `PermissionChecker.php`
- Default: users can only access their own records; admins have full access
- Configure per table in `config/table-rules.php`

### API Endpoint Patterns

**Standard CRUD** (any table discovered from schema):
```
GET    /{table}              — List with pagination, search, sort
GET    /{table}/{id}         — Get single record
POST   /{table}              — Create (validates against table-rules)
PUT    /{table}/{id}         — Update
DELETE /{table}/{id}         — Delete
GET    /{table}/options      — Get foreign key dropdown options
POST   /{table}/bulk         — Bulk create/update/delete (Phase 3.2)
```

**Special Endpoints:**
```
POST   /login                — Auth (returns access + refresh token)
POST   /register             — Register new user
POST   /auth/refresh         — Refresh access token
POST   /auth/password-reset  — Request password reset email
POST   /auth/password-reset/{token} — Confirm password reset
GET    /schema/{table}       — Get table column structure
GET    /{table}              — Supports query params:
         ?search=term        — Full-text search
         ?sort=col1,-col2    — Sort by columns (- for DESC)
         ?fields=id,name     — Select specific fields only
         ?include=rel1,rel2  — Include related data
         ?limit=50&offset=0  — Pagination
```

### Request Headers (Pagination, Caching, Rate Limiting)

```
Authorization: Bearer {token}                    — Required for protected endpoints
X-Pagination-Limit: 50                          — Records per page (default 100, max 1000)
X-Pagination-Offset: 0                          — Page offset
X-Request-ID: {uuid}                            — Request tracing (auto-generated if missing)
Accept-Encoding: gzip                           — Compression support
If-None-Match: "{etag}"                         — 304 Not Modified if unchanged
```

Response headers include rate-limit info:
```
X-RateLimit-Limit: 100                          — Requests allowed per minute
X-RateLimit-Remaining: 95                       — Requests left
X-RateLimit-Reset: 1682345678                   — Unix timestamp when limit resets
```

### Validation & Business Logic

**Table-level rules** in `api/config/table-rules.php`:

```php
'users' => [
    'validation' => [
        'email' => ['type' => 'email', 'required' => true, 'unique' => true],
        'password' => ['minLength' => 8, 'required' => true],
    ],
    'hooks' => [
        'beforeDelete' => function($id, $user, $logger, $db) {
            if ($user->id === $id) throw new \App\RuleException('Cannot delete own account');
        },
        'afterCreate' => function($id, $user, $logger, $db) {
            // Send notification, update related records, etc.
        },
    ]
]
```

Available hook points: `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`.

## Testing

### Frontend Tests
```bash
cd frontend
npm test                  # Run once
npm test -- --watch      # Watch mode
```

Tests use Jasmine/Karma framework. Look for `.spec.ts` files.

### Backend Tests
```bash
cd api
npm test                                    # Run all tests
npm test -- --testFile=tests/path.php     # Run specific test
npm run test:coverage                      # Coverage report in `coverage/`
```

Tests use PHPUnit. Test files in `api/tests/` mirror source structure. Key test patterns:
- Integration tests hit real database (not mocked)
- SQL injection tests verify parameterized queries
- Authorization tests confirm row-level security
- Upload tests verify MIME type, size, content validation

**Run E2E Tests:**
```bash
cd api
bash run-e2e-test.sh          # Full flow: register → login → CRUD → delete
bash run-change-tracking-test.sh  # Verify audit log changes tracked
```

## Configuration Files

### Frontend Configuration
- `frontend/angular.json` — Build config, test config, proxy to backend
- `frontend/tsconfig.json` — TypeScript strict mode enabled
- `frontend/src/app/config/` — API endpoints, auth config

### Backend Configuration
- `api/.env` — Environment variables (local development)
- `api/.env.example` — Template for required env vars
- `api/config/config.local.php` — Database credentials, JWT secret, logging
- `api/config/table-rules.php` — Per-table validation rules and hooks
- `api/phpunit.xml` — Test runner config

**Key Environment Variables:**
```
DB_HOST=localhost                    # MySQL server
DB_USER=root                         # DB user
DB_PASSWORD=                         # DB password
DB_NAME=splk2                        # Database name
JWT_SECRET=...                       # 32-char random (NEVER hardcoded, use env var)
JWT_EXPIRATION=900                   # Token lifetime in seconds (15 min)
CORS_ALLOWED_ORIGINS=http://localhost:4200  # Comma-separated list
MAX_UPLOAD_SIZE_MB=10                # Max file upload size
UPLOAD_ALLOWED_EXTENSIONS=pdf,jpg,png  # Allowed MIME types
RATE_LIMIT_REQUESTS_PER_MINUTE=100   # Per-user rate limit
```

## Git & Branches

Current branch: **master**

**Commit Convention:** Feature branches follow `feature/{name}` or `security/{name}` pattern.

**Recent Work:**
- Phase 1 (Security) ✅ Complete — CORS, JWT, SQL injection, auth, file upload, password reset, rate limiting
- Phase 2 (Design) ✅ Complete (6/7) — API versioning, caching, pagination, field selection, request IDs, relationship expansion
- Phase 3 (Enhancements) — Bulk operations, multi-column sorting completed; remaining: real-time, search, API keys
- Phase 4 (Testing & Docs) — Deferred until Phase 3 features complete

See `NEXT_STEPS.md` for detailed roadmap and progress tracking.

## Common Development Tasks

### Add a New API Endpoint

1. **Create table** in database (or add new table to existing schema)
2. **(Optional) Add validation rules** in `api/config/table-rules.php`:
   ```php
   'my_table' => [
       'validation' => ['field' => ['required' => true, 'minLength' => 2]],
       'hooks' => ['afterCreate' => function($id, $user, $logger, $db) { ... }]
   ]
   ```
3. The API auto-discovers the schema — endpoints are immediately available
4. Test: `curl http://localhost:8000/my_table -H "Authorization: Bearer TOKEN"`

### Add Validation to Frontend

Use Angular reactive forms with validators in the service/component:

```typescript
this.form = this.fb.group({
  email: ['', [Validators.required, Validators.email]],
  name: ['', [Validators.required, Validators.minLength(2)]],
});
```

Backend validates independently via table-rules (always double-validate).

### Debug a Request

1. **Check logs:**
   ```bash
   tail -f api/log/api/app.log           # API logs
   # Check browser DevTools Network tab for request/response
   ```

2. **Check database:**
   ```bash
   mysql -u root splk2
   SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10;  # See all operations
   ```

3. **Check rate limiting:**
   Response includes `X-RateLimit-Remaining` header. If 0, wait until `X-RateLimit-Reset` timestamp.

### Modify Audit Logging

Edit `api/src/AuditAction.php` to track different fields or events. All CRUD logged by default to `audit_logs` table with full before/after state.

### Handle CORS Issues

Edit `api/src/Cors.php` or set `CORS_ALLOWED_ORIGINS` env var:
```bash
CORS_ALLOWED_ORIGINS=http://localhost:4200,http://example.com
```

### Process next step

Follow this workflow to implement features from the roadmap:

1. **Review the roadmap:**
   ```bash
   # Open NEXT_STEPS.md to see all phases and tasks
   # Phase 1 (Security) ✅ Complete
   # Phase 2 (Design) ✅ Complete (6/7 — 2.6 deferred)
   # Phase 3 (Enhancements) — Choose from: 3.1 Real-Time, 3.4 Search, 3.5 API Keys, etc.
   # Phase 4 (Testing & Docs) — Can run in parallel with Phase 3
   ```

2. **Pick a task:**
   - Review the task description, acceptance criteria, and estimated time
   - Briefly explain what the task involves and ask for confirmation before starting

3. **Create feature branch:**
   ```bash
   git checkout -b feature/task-name
   # For security fixes: git checkout -b security/task-name
   ```

4. **Implement and test:**
   - Write code, run tests locally
   - Check tests pass: `npm test` (frontend) or `npm test` (api)
   - Ensure no regressions in other features

5. **Commit and push to develop to trigger CI:**
   ```bash
   git add .
   git commit -m "feat: task description"
   git checkout develop
   git merge feature/task-name
   git push origin develop
   git checkout feature/task-name
   ```
   CI (`api-tests.yml`) only runs on push to `master`, `main`, or `develop` — a plain feature branch push does not trigger it.

6. **Review CI/CD results:**
   - The test run takes ~1–1.5 minutes. Wait, then fetch the dedicated results branch:
     ```bash
     git fetch origin ci-results/develop
     git show origin/ci-results/develop:status.log
     ```
   - Confirm the `commit:` field in `status.log` matches the SHA you just pushed to `develop` (`git rev-parse develop`) — if it's an older SHA, the run hasn't finished yet; wait a bit and re-fetch.
   - If `status: success` ✅:
     ```bash
     git checkout master
     git merge feature/task-name
     git push origin master
     git branch -d feature/task-name
     ```
     Push to `master` automatically — no need to ask for confirmation, the code was already verified on `develop`. This also re-triggers `api-tests.yml` on `master`, but that run is not monitored (no dedicated results branch there). Then update NEXT_STEPS.md and move to the next task.
   - If `status: failure` ❌, read the PHPUnit summary in `status.log`, diagnose the issue, fix, and push again
   - This CI run/results branch exists only for `develop` — `master` has no dedicated results branch and is not used for this feedback loop

7. **Repeat:**
   - Once one task is complete, pick the next item from NEXT_STEPS.md
   - Maintain momentum — each task has estimated time, so you know what to expect

## Troubleshooting

### Frontend Won't Build
```bash
cd frontend
npm ci                   # Clean install from lock file
npm run build
```

### Backend 500 Errors
```bash
# Check logs for details
tail -f api/log/api/app.log

# Verify database is running and accessible
mysql -u root -p
USE splk2;
SHOW TABLES;
```

### 401 Unauthorized Errors
- Token expired: Use refresh endpoint
- Token missing: Check `Authorization: Bearer` header
- Invalid token: Re-login to get new token

### 403 Forbidden (Permission Denied)
- User doesn't have role permission (check `RoleBasedAccessControl.php`)
- Row-level security: User trying to access another user's record
- Check `PermissionChecker.php` for permission logic

### SQL Injection Test Failed
All WHERE clauses use `WhereClauseBuilder.php` with parameterized queries. Query string parameters are never interpolated. If new endpoint added, ensure it uses parameterized statements.

### Tests Failing
```bash
# Frontend
cd frontend && npm test -- --no-watch --browsers=ChromeHeadless

# Backend
cd api && npm test
# If specific test, check phpunit.xml configuration and .env.test
```

## Performance Considerations

1. **API Response Times:**
   - List endpoints paginated by default (limit 100, max 1000)
   - Use `?fields=id,name` to reduce payload size
   - Use `?include=` sparingly (loads related data — watch for N+1)
   - Add MySQL indexes on frequently filtered columns

2. **Caching:**
   - Browser caches responses via ETag headers
   - Server returns 304 Not Modified if unchanged
   - Configure `Cache-Control` header per endpoint as needed

3. **Database:**
   - Check slow query log: `SET GLOBAL slow_query_log=ON; SET GLOBAL long_query_time=0.5;`
   - View running queries: `SHOW PROCESSLIST;`
   - Add indexes for common search filters

4. **Frontend:**
   - Angular uses OnPush change detection where possible
   - Build optimized: `npm run build` (not `--dev`)
   - Check bundle size: `npm run build -- --stats-json`

## Useful Resources

- **API Docs:** `api/openapi.yaml` (OpenAPI 3.0 spec)
- **Reusability Guide:** `api/REUSABLE.md` (how to use API in other projects)
- **E2E Test Examples:** `api/requests/` (curl examples and requests)
- **Roadmap:** `NEXT_STEPS.md` (detailed Phase 1-4 plan with progress)
- **Session Summary:** `SESSION_SUMMARY.md` (prior work context)

## Project instructions

### Developer preferences

- User is a Czech native speaker and may write requests in Czech.
- Keep responses concise.
- Prefer minimal, targeted changes over broad refactors.
- Preserve existing architecture and coding style.
- Before changing code, briefly explain the likely cause.
- After changing code, summarize what changed and how to verify it.
- Do not rewrite unrelated code.
- Do not introduce new dependencies unless clearly justified.
- Use clear commit messages.

### Workflow

- First inspect the relevant files.
- Propose a small plan before larger changes.
- Prefer patches/diffs over large rewritten files.
- Run existing tests/build/lint when available.
- If tests are missing, state what was checked manually.
- Ask before destructive operations.

### Token saving

- Be brief.
- Avoid long recaps.
- Do not repeat unchanged code.
- Do not explain obvious syntax.
- Mention alternatives only when they materially matter.