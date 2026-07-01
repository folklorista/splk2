# SPLK2 API - Security & Enhancement Roadmap

**Status**: Based on comprehensive security audit
**Last Updated**: 2026-07-01
**Total Tasks**: 27 tasks across 4 phases
**Estimated Effort**: 95-125 hours
**Audit Report**: Available in audit visualization

---

## 🔴 Phase 1: Security Critical (MUST FIX BEFORE PRODUCTION)

These 8 tasks MUST be completed before any production deployment. They address critical vulnerabilities.

### 1.1 Fix CORS Configuration ⚠️ CRITICAL
- **File:** `api/src/Cors.php`
- **Issue:** `Access-Control-Allow-Origin: *` allows ALL domains
- **Task:**
  - Add `CORS_ALLOWED_ORIGINS` env variable (comma-separated list)
  - Parse and validate origin on each request
  - Return 403 Forbidden for disallowed origins
  - Update `.env.example`
- **Acceptance Criteria:**
  - ✓ Requests from allowed origins work
  - ✓ Requests from disallowed origins return 403
  - ✓ Works with multiple origins in env
- **Estimated Time:** 30 minutes
- **Files to Change:**
  - api/src/Cors.php
  - api/.env.example (add CORS_ALLOWED_ORIGINS)

### 1.2 Fix JWT Token Expiration ⚠️ CRITICAL  
- **File:** `api/src/Auth.php` (line 45)
- **Issue:** Tokens valid for 365 days (should be 15 minutes)
- **Task:**
  - Change expiration from `365 * 24 * 60 * 60` to `15 * 60` (900 seconds)
  - Create `refresh_tokens` table (token_hash, user_id, expires_at, created_at, revoked)
  - Implement `POST /auth/refresh` endpoint with refresh token
  - Return new access token + new refresh token
  - Support token rotation (invalidate old refresh token)
  - Add `JWT_EXPIRATION` env variable (default 900 seconds)
- **Acceptance Criteria:**
  - ✓ New tokens expire in 15 minutes
  - ✓ Refresh endpoint returns new tokens
  - ✓ Old refresh tokens are invalidated after use
  - ✓ Expired tokens return 401
- **Estimated Time:** 2-3 hours
- **Files to Change:**
  - api/src/Auth.php
  - api/migrations/ (new migration for refresh_tokens table)
  - api/public/index.php (new route /auth/refresh)
  - api/.env.example

### 1.3 Fix Hardcoded JWT Secret ⚠️ CRITICAL
- **File:** `api/config/config.local.php` (line 13)
- **Issue:** Default secret `'my_little_secret'` is weak and publicly visible
- **Task:**
  - Remove hardcoded default value from config
  - Make `JWT_SECRET` REQUIRED environment variable (throw error if missing)
  - Generate 32-character random secret for local development guide
  - Update `.env.example` with placeholder
  - Document secure secret generation
- **Acceptance Criteria:**
  - ✓ No default value in code
  - ✓ Missing JWT_SECRET throws clear error
  - ✓ Documentation shows how to generate secure secret
  - ✓ API won't start without JWT_SECRET in .env
- **Estimated Time:** 30 minutes
- **Files to Change:**
  - api/config/config.local.php
  - api/.env.example
  - api/README.md or docs/SETUP.md

### 1.4 Fix SQL Injection Vulnerabilities ⚠️ CRITICAL
- **File:** `api/src/Database.php` (multiple locations)
- **Issue:** WHERE clauses use string interpolation, not parameterized queries
- **Affected Areas:**
  - Line 98: getAll() WHERE clause
  - Line 125: COUNT query WHERE clause
  - public/index.php line 346: audit_logs filtering
  - Multiple other where clause constructions
- **Task:**
  - Create helper method `buildSafeWhereClause()` that accepts array of conditions and binds parameters
  - Refactor all WHERE clause construction to use prepared statements
  - Use PDO placeholder binding (`:param` syntax)
  - Test with SQL injection payloads
  - Update audit_logs endpoint to use parameterized queries
- **Acceptance Criteria:**
  - ✓ All WHERE clauses use prepared statements
  - ✓ No string interpolation in SQL
  - ✓ SQL injection payloads are safely escaped
  - ✓ Tests verify security
- **Estimated Time:** 4-5 hours
- **Files to Change:**
  - api/src/Database.php
  - api/public/index.php (audit_logs endpoint)
  - api/tests/ (add SQL injection test cases)

### 1.5 Secure File Upload Validation ⚠️ CRITICAL
- **File:** `api/src/FileUploadManager.php`
- **Issue:** Missing MIME type, size, and content validation
- **Task:**
  - Add MIME type whitelist (MIME + extension matching)
  - Enforce maximum file size (10MB default, configurable)
  - Validate actual file content (magic bytes) vs extension
  - Prevent executable content detection
  - Store files outside web root (not in /public/)
  - Generate random filenames (UUID + extension)
  - Add virus scanning stub (ready for ClamAV integration)
  - Log upload attempts and validation failures
- **Configuration:**
  - MAX_UPLOAD_SIZE_MB env var (default 10)
  - UPLOAD_ALLOWED_EXTENSIONS env var
- **Acceptance Criteria:**
  - ✓ .exe, .php, .sh files rejected
  - ✓ Files larger than limit rejected
  - ✓ MIME type mismatch detected
  - ✓ Filenames are randomized, not user input
  - ✓ Files stored outside webroot
- **Estimated Time:** 3-4 hours
- **Files to Change:**
  - api/src/FileUploadManager.php
  - api/.env.example
  - api/tests/ (upload security tests)

### 1.6 Implement Authorization on CRUD Endpoints ⚠️ CRITICAL
- **File:** `api/public/index.php` (lines 327-426), `api/src/Endpoints.php`
- **Issue:** No fine-grained permission checks - any authenticated user can access any data
- **Task:**
  - Define resource permissions matrix per role and table
  - Create authorization middleware that checks:
    - Does user have role with permission?
    - Is user the owner of the record (for personal data)?
  - Implement row-level security (users can only access own records by default)
  - Add table-level access control config
  - Create permission checking helper methods
  - Enforce on all CRUD operations
- **Permission Matrix Example:**
  ```
  users table:
    - admin: can read/write all, delete all
    - user: can read own, write own, cannot delete
    - guest: can read own only (no write/delete)
  
  items table:
    - admin: full access
    - user: can read/write/delete own items
    - guest: can read all, cannot write/delete
  ```
- **Acceptance Criteria:**
  - ✓ User can only access their own data (by default)
  - ✓ Admin can access all data
  - ✓ Non-authorized requests return 403 Forbidden
  - ✓ Audit logs track permission checks
- **Estimated Time:** 5-6 hours
- **Files to Change:**
  - api/src/Endpoints.php (add authorization checks)
  - api/public/index.php (add middleware)
  - api/config/table-rules.php (or new config file for permissions)
  - api/tests/ (authorization tests)

### 1.7 Implement Password Reset Mechanism ⚠️ CRITICAL
- **Files:** New: `api/src/PasswordReset.php`, Updated: `api/public/index.php`
- **Issue:** No way to recover lost passwords
- **Task:**
  - Create `password_reset_tokens` table (token_hash, user_id, expires_at, used_at, created_at)
  - Implement `POST /auth/password-reset` endpoint:
    - Accept email
    - Generate secure random token (32 chars)
    - Store token_hash in database with 1 hour expiry
    - Send token via email
    - Rate limit to 3 per hour per email
  - Implement `POST /auth/password-reset/{token}` endpoint:
    - Accept new password
    - Verify token exists and hasn't expired
    - Verify token hasn't been used
    - Update password, mark token as used
    - Return 400 if token expired/invalid/used
  - Add audit logging for password resets
  - Send confirmation email on successful reset
- **Acceptance Criteria:**
  - ✓ User receives reset email with token
  - ✓ Token expires in 1 hour
  - ✓ Token can only be used once
  - ✓ New password works after reset
  - ✓ Used tokens are invalidated
- **Estimated Time:** 3-4 hours
- **Files to Change:**
  - api/migrations/ (new migration for password_reset_tokens)
  - api/src/PasswordReset.php (new class)
  - api/public/index.php (new routes)
  - api/tests/ (password reset tests)

### 1.8 Fix Rate Limiting Issues ⚠️ HIGH
- **File:** `api/public/index.php`, `api/src/RateLimiter.php`
- **Issues:**
  - Can bypass with `?skipRateLimit=1` query parameter (no auth check)
  - Login endpoint exempt from rate limiting (brute force vector)
  - Rate limiting based on IP only (easy to spoof)
- **Task:**
  - Remove `skipRateLimit` bypass entirely
  - Apply rate limiting to login/register endpoints
  - Implement role-based rate limiting:
    - guest: 10 requests/minute
    - user: 100 requests/minute
    - admin: 1000 requests/minute
  - Use user ID for authenticated requests instead of IP
  - Add DDoS detection (sudden spike in requests from IP/user)
  - Add X-RateLimit-* headers to all responses
- **Acceptance Criteria:**
  - ✓ Login endpoint rate limited
  - ✓ `?skipRateLimit` doesn't work
  - ✓ Admins can make more requests
  - ✓ Proper headers returned
- **Estimated Time:** 2-3 hours
- **Files to Change:**
  - api/src/RateLimiter.php
  - api/public/index.php
  - api/.env.example (role-based rate limits)

---

## 🟠 Phase 2: High Priority Design Issues

These improve API usability, maintainability, and performance.

### 2.1 API Versioning
- **Estimated Time:** 4-5 hours
- **Description:** Implement `/api/v1/`, `/api/v2/` endpoint versioning strategy
- **Details in NEXT_STEPS.md section 2.1**

### 2.2 Response Caching & ETag Support  
- **Estimated Time:** 3-4 hours
- **Description:** Add Cache-Control, ETag, Last-Modified headers

### 2.3 Enforce Pagination Defaults & Limits
- **Estimated Time:** 2 hours
- **Description:** Default limit 100, max 1000 records

### 2.4 Field Selection / Sparse Fieldsets
- **Estimated Time:** 3 hours
- **Description:** Support `?fields=id,name,email` parameter

### 2.5 Request Correlation IDs
- **Estimated Time:** 1-2 hours
- **Description:** Add X-Request-ID header for request tracing

### 2.6 Webhook Payload Signing with HMAC
- **Estimated Time:** 2-3 hours
- **Description:** Sign webhooks with X-Webhook-Signature header

### 2.7 Relationship Expansion (`?include`)
- **Estimated Time:** 4-5 hours
- **Description:** Support `?include=roles,permissions` for nested data

---

## 🟡 Phase 3: Medium Priority Enhancements

### 3.1 WebSocket / Real-Time Support
- **Option A - Server-Sent Events (SSE):** 3-4 hours
- **Option B - WebSocket (Ratchet):** 8-10 hours
- **Recommendation:** Start with SSE (simpler, client-side compatible)

### 3.2 Bulk Operations
- **Estimated Time:** 3-4 hours
- **Description:** `POST /users/bulk`, `PATCH /users/bulk`, `DELETE /users/bulk`

### 3.3 Multiple Column Sorting
- **Estimated Time:** 1-2 hours
- **Description:** Support `?sort=last_name,-first_name`

### 3.4 Enhanced Search Functionality
- **Estimated Time:** 5-6 hours
- **Description:** Fuzzy matching, boolean operators, faceted search

### 3.5 API Key Authentication
- **Estimated Time:** 4-5 hours
- **Description:** Support `Authorization: Bearer <api-key>` for service-to-service

### 3.6 Refresh Token Management
- **Estimated Time:** 2-3 hours
- **Description:** Token rotation, revocation, expiration tracking

### 3.7 Auto-Generate OpenAPI Documentation
- **Estimated Time:** 6-8 hours
- **Description:** Parse PHP annotations, auto-generate spec

### 3.8 Export / Batch Download
- **Estimated Time:** 2-3 hours
- **Description:** CSV/JSON export with filters and field selection

---

## 📊 Phase 4: Testing & Documentation

### 4.1 Comprehensive Test Suite
- **Estimated Time:** 10-15 hours
- **Target:** 80%+ code coverage
- **Includes:** Unit tests, integration tests, security tests, performance tests

### 4.2 Security Tests
- **Estimated Time:** 5-6 hours
- **Includes:** SQL injection, CORS, authorization, rate limiting tests

### 4.3 API Documentation
- **Estimated Time:** 8-10 hours
- **Includes:** Usage examples, auth guide, error reference, security guide

### 4.4 Deployment Guide
- **Estimated Time:** 4-5 hours
- **Includes:** Environment setup, database migrations, SSL/TLS config

---

## 🐛 Known Issues

### Default multi-column `?search=` can hit a MySQL FULLTEXT error
- **Discovered:** 2026-07-01, while implementing 3.4 Enhanced Search.
- **Symptom:** When a search request doesn't specify `X-Search-Columns`, the API defaults to searching every "searchable" column on the table (`Database::getSearchableColumns()`), then runs a single `MATCH(col1, col2, ...) AGAINST (...)`. MySQL requires one FULLTEXT index covering exactly that column set — none of the current schemas have one for the full default set (e.g. `items` has FULLTEXT only on `inventory_number`, not `status`; `users` has two separate single-column FULLTEXT indexes, not one composite). The query fails with error 1191.
- **Why it wasn't caught before:** no test exercised default (column-less) search until 3.4; the new `EnhancedSearchE2ETest` works around it by always passing `X-Search-Columns` explicitly.
- **Fix idea for later:** either restrict the default search-column set to columns actually covered by a single FULLTEXT index, or issue one `MATCH()` per FULLTEXT index and OR the results together.

---

## ✅ Resolved Issues

### RBAC test failures when full suite runs together — RESOLVED 2026-07-01
- **Discovered:** 2026-07-01, while fixing CI logging on `develop` (`.github/workflows/api-tests.yml`)
- **Symptom:** 10 tests failed (`Admin can create users`, `Regular user cannot create users`, `Admin can delete users`, `Regular user cannot delete users`, `User can update own profile`, `User cannot update other users`, `Admin cannot delete own account`, `Admin can create roles`, `Regular user cannot create roles`, `Admin cannot delete built-in roles`) — only in the "Run All Tests with Coverage" step, which runs the whole suite together.
- **Root causes found** (not test isolation — three real bugs):
  1. `beforeCreate` hook was called with only 3 args in `Endpoints.php` while some `table-rules.php` closures expect 4 (`$db`) — `ArgumentCountError`.
  2. Security bug in `table-rules.php`: admin self-delete guard used strict `===` comparing `$user->id` (int) to `$id` from the URL (string), so the check never matched and admins could delete their own account.
  3. `RBACTest.php` had stale assertions (expected `200` instead of `201` on create endpoints; checked the wrong response field for permission-denial messages).
- **Fix:** cast `$id` to int before comparison, pass `$db` to the hook call, correct the test assertions, and replace `time()`-based test emails with `uniqid()` to remove an unrelated collision source when tests run back-to-back.
- **Why it went unnoticed:** the coverage step has `continue-on-error: true`, and a separate bug (invalid `--verbose` PHPUnit 11 flag) meant this step's output wasn't even being captured — the job reported `status: success` regardless.

---

## 📈 Summary & Effort Breakdown

| Phase | Category | Tasks | Estimated Hours | Critical |
|-------|----------|-------|-----------------|----------|
| 1 | Security | 8 tasks | 20-25 hrs | ✅ YES |
| 2 | Design | 7 tasks | 20-25 hrs | ✅ YES |
| 3 | Enhancement | 8 tasks | 30-40 hrs | ❌ NO |
| 4 | Testing | 4 tasks | 25-35 hrs | ❌ NO |
| **TOTAL** | | **27 tasks** | **95-125 hrs** | |

---

## 🎯 Recommended Phasing Strategy

### Sprint 1 (Week 1-2): Security Critical
- **Goal:** Fix all security vulnerabilities
- **Tasks:** Phase 1 (all 8 tasks)
- **Effort:** 20-25 hours
- **Outcome:** API safe for production
- **Deliverables:**
  - Secure CORS
  - Fixed JWT expiration + refresh tokens
  - Removed hardcoded secrets
  - SQL injection patches
  - File upload validation
  - Authorization checks
  - Password reset flow
  - Rate limiting improvements

### Sprint 2 (Week 3-4): Design & Usability
- **Goal:** Improve API design and developer experience
- **Tasks:** Phase 2 (7 tasks)
- **Effort:** 20-25 hours
- **Outcome:** Professional, well-designed API
- **Deliverables:**
  - API versioning strategy
  - Caching headers
  - Pagination enforcement
  - Field selection
  - Request tracing
  - Webhook signing
  - Relationship expansion

### Sprint 3 (Week 5-8): Enhancements
- **Goal:** Add advanced features
- **Tasks:** Phase 3 (8 tasks, prioritize by need)
- **Effort:** 30-40 hours
- **Outcome:** Feature-rich API
- **Priorities:**
  1. Real-time (SSE)
  2. API keys
  3. Bulk operations
  4. Enhanced search

### Sprint 4 (Parallel): Testing & Documentation
- **Goal:** Ensure quality and maintainability
- **Tasks:** Phase 4 (4 tasks)
- **Effort:** 25-35 hours
- **Outcome:** Production-ready with full documentation
- **Run in parallel** with Sprint 3

---

## 🚀 Starting Now: Phase 1 Execution

### Next Immediate Steps

1. **Read the audit report** (in visualization)
2. **Start with Task 1.4 (SQL Injection)** - affects many components
   - High impact
   - Foundation for other security work
   - Takes 4-5 hours but unlocks cleanup of related issues

3. **Then tackle in this order:**
   - 1.1 CORS (30 min, quick win)
   - 1.3 JWT Secret (30 min, quick security fix)
   - 1.2 JWT Expiration + Refresh (2-3 hours, foundational)
   - 1.5 File Upload (3-4 hours, critical protection)
   - 1.6 Authorization (5-6 hours, important for multi-user)
   - 1.7 Password Reset (3-4 hours, user feature)
   - 1.8 Rate Limiting (2-3 hours, cleanup)

### For Each Task
- Create feature branch: `git checkout -b security/task-name`
- Implement changes
- Write tests for new functionality
- Update documentation
- Create PR with description of changes
- Get code review
- Merge when tests pass

---

## 📝 Progress Tracking

As you complete each task, mark it here:

### Phase 1: Security Critical ✅ COMPLETE
- [x] 1.1 Fix CORS Configuration ✅ COMPLETE
- [x] 1.2 Fix JWT Token Expiration ✅ COMPLETE
- [x] 1.3 Fix Hardcoded JWT Secret ✅ COMPLETE
- [x] 1.4 Fix SQL Injection Vulnerabilities ✅ COMPLETE
- [x] 1.5 Secure File Upload Validation ✅ COMPLETE
- [x] 1.6 Implement Authorization on CRUD ✅ COMPLETE
- [x] 1.7 Implement Password Reset ✅ COMPLETE
- [x] 1.8 Fix Rate Limiting Issues ✅ COMPLETE

**🎉 PHASE 1 COMPLETE: 8/8 tasks (100%)**
**API is now production-ready from security perspective!**

### Phase 2: High Priority Design (6/7 — 2.6 moved to wishlist)
- [x] 2.1 API Versioning ✅ COMPLETE
- [x] 2.2 Response Caching & ETag ✅ COMPLETE
- [x] 2.3 Enforce Pagination ✅ COMPLETE
- [x] 2.4 Field Selection ✅ COMPLETE
- [x] 2.5 Request Correlation IDs ✅ COMPLETE
- [ ] 2.6 Webhook Signing — deferred, see [Wishlist](#-wishlist--deferred-ideas)
- [x] 2.7 Relationship Expansion ✅ COMPLETE

### Phase 3: Enhancements (As needed)
- [x] 3.1 Real-Time Support ✅ COMPLETE
- [x] 3.2 Bulk Operations ✅ COMPLETE
- [x] 3.3 Multiple Column Sorting ✅ COMPLETE
- [x] 3.4 Enhanced Search ✅ COMPLETE
- [ ] 3.5 API Keys
- [ ] 3.6 Refresh Token Management
- [ ] 3.7 Auto-Generate OpenAPI
- [ ] 3.8 Export / Batch Download

### Phase 4: Testing & Documentation (Parallel)
- [ ] 4.1 Comprehensive Tests
- [ ] 4.2 Security Tests
- [ ] 4.3 API Documentation
- [ ] 4.4 Deployment Guide

---

## 💭 Wishlist / Deferred Ideas

Features that are worth doing eventually but don't block the current roadmap. Pull from here when Phase 3 is done or when a specific need arises.

### Webhook Payload Signing with HMAC (was 2.6)
- **Estimated Time:** 2-3 hours
- **Description:** Sign webhooks with `X-Webhook-Signature` header so consumers can verify payload authenticity/integrity.
- **Why deferred:** No active webhook consumer needs verified signatures yet; revisit once a real external subscriber exists.

### GraphQL Layer over the REST API
- **Estimated Time:** TBD (needs its own spike/estimate)
- **Description:** Add a GraphQL endpoint on top of the existing REST API, primarily to solve chained/nested relationship queries (e.g. `A -> B -> C`) in a single request — something `?include=` only handles one hop at a time.
- **Why deferred:** Bigger architectural decision (schema design, resolver strategy, N+1 handling) than a normal roadmap task; needs dedicated design time before estimating.

---

## 📞 Questions?

For detailed implementation guidance on any task, check the corresponding section above or ask during implementation sessions.

**Status:** Phase 1 & 2 complete (2.6 deferred to wishlist). Phase 3 in progress — 3.1, 3.2, 3.3 and 3.4 done, CI green.
**Next Task:** Pick from Phase 3 (3.5 API Keys, 3.6 Refresh Tokens, 3.7 Auto-Generate OpenAPI, or 3.8 Export/Batch Download).
