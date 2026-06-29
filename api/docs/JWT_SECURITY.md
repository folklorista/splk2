# JWT Security Configuration Guide

## Overview

JWT (JSON Web Token) Secret is now **required** and must be explicitly configured via environment variable. There are **no hardcoded defaults**.

## Security Changes

### Before (UNSAFE ❌)
```php
'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'my_little_secret',
```

**Vulnerability:** Default hardcoded secret `'my_little_secret'` was weak and visible in source code.

### After (SECURE ✅)
```php
// Validates JWT_SECRET is configured
if (empty($_ENV['JWT_SECRET'])) {
    throw new \Exception('JWT_SECRET environment variable is not set...');
}
'jwt_secret' => $_ENV['JWT_SECRET'],
```

**Security:** 
- No hardcoded defaults
- Must be explicitly set
- API refuses to start without it
- Forces secure secret generation

## Configuration

### Generate a Secure Secret

Use a cryptographically secure random generator:

```bash
# Using OpenSSL (recommended)
openssl rand -base64 32

# Using PHP
php -r 'echo base64_encode(random_bytes(32));'

# Using Python
python3 -c 'import secrets; print(secrets.token_urlsafe(32))'
```

Example output:
```
aBcDeFgHiJkLmNoPqRsTuVwXyZaBcDeFgHiJk1234567890
```

### Set in .env File

```bash
# Local Development
JWT_SECRET=your-generated-secret-here

# Production
JWT_SECRET=very-long-random-secret-from-secure-generator
```

### .env.example Reference

```bash
# JWT Secret (REQUIRED - generate a strong random value)
# IMPORTANT: Must be at least 32 characters for security
# Generate secure secret: openssl rand -base64 32
# DO NOT use the example below in production
JWT_SECRET=your-very-long-random-secret-at-least-32-characters
```

## Startup Validation

When the API starts, it validates JWT_SECRET:

```bash
# If JWT_SECRET is missing:
PHP Fatal error:  Uncaught Exception: Critical Configuration Error: 
JWT_SECRET environment variable is not set.
This is required for API security.
Please add JWT_SECRET to your .env file.
Example: JWT_SECRET=your-very-long-random-secret-at-least-32-chars
```

**Resolution:** Add JWT_SECRET to your `.env` file

## Best Practices

### DO ✅

```bash
# Long, random secret (32+ characters)
JWT_SECRET=aBcDeFgHiJkLmNoPqRsTuVwXyZaBcDeFgHiJk1234567890

# For each environment
# .env (local):     JWT_SECRET=dev-secret-here
# .env.production:  JWT_SECRET=prod-secret-here (different value!)

# Regenerate if compromised
openssl rand -base64 32
```

### DON'T ❌

```bash
# Don't use simple/weak secrets
JWT_SECRET=password
JWT_SECRET=12345678
JWT_SECRET=secret

# Don't share between environments
# (local, staging, production should each have unique secrets)
JWT_SECRET=same-secret-everywhere  # WRONG!

# Don't commit .env to git
# (it contains secrets)
git add .env  # WRONG!

# Don't hardcode in PHP
'jwt_secret' => 'hardcoded_value',  // WRONG!
```

## How JWT Secret is Used

### Token Generation

When user logs in:

1. Server creates JWT payload with user data
2. Server signs payload with JWT_SECRET
3. Signed token sent to client

```php
$token = JWT::encode($payload, $this->config['jwt_secret'], 'HS256');
```

### Token Verification

On subsequent requests:

1. Client sends token in `Authorization: Bearer <token>` header
2. Server verifies signature using JWT_SECRET
3. If signature matches, token is valid
4. If signature doesn't match, token is rejected

```php
$decoded = JWT::decode($token, new Key($this->config['jwt_secret'], 'HS256'));
```

## Secret Rotation

If your JWT_SECRET is compromised:

1. **Generate new secret:**
   ```bash
   openssl rand -base64 32
   ```

2. **Update .env:**
   ```bash
   JWT_SECRET=new-secret-here
   ```

3. **Restart API**

4. **Users must re-login** (old tokens become invalid)

**Impact:** All existing tokens become invalid - users must log in again.

## Secret Management in Production

### Using Environment Variables

```bash
# Deploy via environment variable (not in repo)
export JWT_SECRET="production-secret-from-secure-vault"
php -S localhost:8000 -t public
```

### Using Secrets Management Tools

```bash
# AWS Secrets Manager
AWS_SECRET_NAME=api/jwt-secret
JWT_SECRET=$(aws secretsmanager get-secret-value --secret-id $AWS_SECRET_NAME | jq -r .SecretString)

# Docker Secrets
docker run --secret jwt_secret myapp

# Kubernetes
kubectl create secret generic api-jwt --from-literal=secret=value
```

### Using .env Files (Local Only)

```bash
# .env (local development only)
JWT_SECRET=dev-secret

# .env.production (production - separate machine)
JWT_SECRET=prod-secret
```

**Important:** Each environment should have a different secret!

## Testing

### Unit Tests

Run JWT configuration tests:

```bash
JWT_SECRET=test-secret ./vendor/bin/phpunit tests/Unit/JwtSecretConfigTest.php
```

Tests verify:
- ✅ JWT_SECRET is required
- ✅ Empty JWT_SECRET throws error
- ✅ Valid JWT_SECRET is accepted
- ✅ No hardcoded secrets in code
- ✅ Error messages are helpful

### Manual Testing

```bash
# Verify API won't start without JWT_SECRET
unset JWT_SECRET
php -S localhost:8000 -t public
# Should fail with configuration error

# Verify API starts with JWT_SECRET
export JWT_SECRET="test-secret-here"
php -S localhost:8000 -t public
# Should start successfully
```

## Troubleshooting

### Error: "JWT_SECRET environment variable is not set"

**Cause:** Environment variable not configured

**Solution:**
```bash
# Add to .env
echo "JWT_SECRET=your-secret-here" >> .env

# Or set as environment variable
export JWT_SECRET="your-secret-here"
```

### Error: "Invalid token" or "Signature verification failed"

**Cause:** Token signed with different secret

**Possible reasons:**
- Secret was changed (users must re-login)
- Token from different API instance (use same secret)
- Token from old environment (secrets differ per environment)

**Solution:**
- Verify JWT_SECRET matches
- Request new token (login again)
- Check token not from wrong environment

### Tokens become invalid after restart

**Cause:** JWT_SECRET changed

**This is expected behavior** - old tokens use old secret, new secret won't verify them.

**Solution:**
- Document secret change in deploy notes
- Inform users they need to re-login
- In critical systems, update secret gradually

## Integration with Auth System

```php
// In Auth.php:
$payload = [
    'iss' => "localhost",
    'sub' => $user['id'],
    'iat' => time(),
    'exp' => time() + 900,  // 15 minutes
    'user' => [...],
];
return JWT::encode($payload, $this->config['jwt_secret'], 'HS256');

// Verification:
$decoded = JWT::decode($token, new Key($this->config['jwt_secret'], 'HS256'));
```

## Related Security Features

- **Token Expiration:** 15 minutes (Task 1.2)
- **Refresh Tokens:** Extend session without re-login (Task 1.2)
- **CORS Control:** Only allowed origins (Task 1.1)
- **Rate Limiting:** Protect against brute-force (Task 1.8)

## References

- [JWT Introduction (JWT.io)](https://jwt.io/introduction)
- [HMAC-SHA256 (RFC 4868)](https://tools.ietf.org/html/rfc4868)
- [OWASP: Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [Firebase JWT Library](https://github.com/firebase/php-jwt)
