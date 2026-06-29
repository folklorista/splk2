# API Testing Strategy

## Overview

This document outlines the testing strategy for SPLK2 REST API, focusing on the new Rules system, validation framework, and CRUD operations.

## Test Pyramid

```
        /\
       /  \        Integration Tests (API layer, full requests)
      /____\       ├─ CRUD operations with rules
                   ├─ Tree operations
                   ├─ Auth flow
                   └─ Error scenarios

     /      \
    / Unit   \     Unit Tests (Business logic)
   /  Tests   \    ├─ RuleValidator
  /____________\   ├─ Validation constraints
                   └─ Hook execution

 /          \
/ Database   \     Database/Fixtures
/   Fixtures  \    ├─ Test database schema
/______________\   ├─ Sample data
                   └─ Cleanup between tests
```

## Testing Framework Setup

### Technology Stack

- **Framework**: PHPUnit 11.x
- **HTTP Client**: cURL (native PHP)
- **Database**: MySQL 8.0 (test-specific database)
- **Fixtures**: Plain SQL + seed classes

### Installation

```bash
cd /api
composer require --dev phpunit/phpunit:^11.0
composer require --dev guzzlehttp/guzzle:^7.0  # Optional: for easier HTTP testing
```

### Directory Structure

```
/api/tests/
├── README.md                    # This file
├── phpunit.xml                  # PHPUnit configuration
├── bootstrap.php                # Test environment setup
├── Unit/
│   ├── RuleValidatorTest.php
│   ├── ConstraintValidationTest.php
│   └── HookExecutionTest.php
├── Integration/
│   ├── AuthFlowTest.php
│   ├── CrudOperationsTest.php
│   ├── TreeOperationsTest.php
│   ├── SearchTest.php
│   └─ ErrorHandlingTest.php
├── Fixtures/
│   ├── DatabaseSeeder.php       # Populate test data
│   ├── TestCase.php             # Base test class with helpers
│   └── sample-data.sql          # SQL fixture data
└── .env.test                    # Test database config
```

## Unit Tests

Focus: Business logic, validation, rules, hooks.

### 1. RuleValidator Tests

**File**: `Unit/RuleValidatorTest.php`

```php
<?php
namespace SPLK2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\RuleValidator;
use App\Database;
use App\Logger;

class RuleValidatorTest extends TestCase
{
    private RuleValidator $validator;
    private Database $db;
    private Logger $logger;

    protected function setUp(): void
    {
        // Load rules config
        $rules = require __DIR__ . '/../../config/table-rules.php';
        $this->db = // Mock or test DB
        $this->logger = // Mock logger
        $this->validator = new RuleValidator($rules, $this->db, $this->logger);
    }

    // Test Cases
    public function testValidateEmailRequired()
    {
        $result = $this->validator->validateCreate('users', ['password' => 'pass']);
        $this->assertFalse($result['valid']);
        $this->assertContains('email is required', $result['errors']);
    }

    public function testValidateEmailFormat()
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'invalid-email',
            'password' => 'pass123456',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        $this->assertFalse($result['valid']);
    }

    public function testValidatePasswordMinLength()
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'user@example.com',
            'password' => 'short',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        $this->assertFalse($result['valid']);
        $this->assertContains('at least 8', implode(' ', $result['errors']));
    }

    public function testValidPassesAllConstraints()
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'user@example.com',
            'password' => 'securePass123',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        $this->assertTrue($result['valid']);
    }
}
```

### 2. Hook Execution Tests

**File**: `Unit/HookExecutionTest.php`

```php
<?php
namespace SPLK2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\RuleValidator;

class HookExecutionTest extends TestCase
{
    public function testBeforeDeleteHookPreventsAdminSelfDelete()
    {
        $validator = new RuleValidator($this->getRules(), $this->db, $this->logger);
        
        $currentUser = (object)['id' => 1, 'email' => 'admin@example.com'];
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot delete your own account');
        
        // This should throw exception
        $validator->executeHook('users', 'beforeDelete', 1, $currentUser, $this->logger, $this->db);
    }

    public function testAfterDeleteHookCleansupUserGroups()
    {
        // Test that deleting user removes them from groups
        $validator = new RuleValidator($this->getRules(), $this->db, $this->logger);
        
        // Setup: User in group
        $this->db->insert('users_groups', ['user_id' => 5, 'group_id' => 1]);
        
        // Execute hook
        $validator->executeHook('users', 'afterDelete', 5, $this->currentUser, $this->logger, $this->db);
        
        // Assert: User no longer in group
        $result = $this->db->getAll('users_groups', 'user_id = 5');
        $this->assertEmpty($result['data']);
    }
}
```

### Test Coverage Goals

- **Unit Tests**: 85%+ coverage of RuleValidator, validation logic
- **Critical Paths**: 100% coverage of security-related code (SQL injection fixes, auth)

---

## Integration Tests

Focus: Full API requests, CRUD operations with rules, error scenarios.

### 1. Auth Flow Test

**File**: `Integration/AuthFlowTest.php`

```php
<?php
namespace SPLK2\Tests\Integration;

class AuthFlowTest extends TestCase
{
    public function testLoginWithValidCredentials()
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'correctPassword123'
        ]);
        
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('token', $response['data']);
        
        // Token should be valid JWT
        $this->assertTrue($this->isValidJWT($response['data']['token']));
    }

    public function testLoginWithInvalidPassword()
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrongPassword'
        ]);
        
        $this->assertEquals(401, $response['status']);
        $this->assertNull($response['data']);
    }

    public function testRegisterNewUser()
    {
        $response = $this->post('/register', [
            'email' => 'newuser@example.com',
            'password' => 'securePass123',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    public function testRegisterWithDuplicateEmail()
    {
        $this->post('/register', [
            'email' => 'dup@example.com',
            'password' => 'pass123456',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        // Try again with same email
        $response = $this->post('/register', [
            'email' => 'dup@example.com',
            'password' => 'pass123456',
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertContains('already exists', $response['error']);
    }
}
```

### 2. CRUD Operations Test

**File**: `Integration/CrudOperationsTest.php`

```php
<?php
class CrudOperationsTest extends TestCase
{
    private string $token;
    private array $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Login and get token
        $response = $this->post('/login', [
            'email' => 'testuser@example.com',
            'password' => 'TestPassword123'
        ]);
        $this->token = $response['data']['token'];
    }

    public function testCreateUserWithValidation()
    {
        $response = $this->post('/users', [
            'email' => 'newuser@example.com',
            'password' => 'securePass123',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ], ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(201, $response['status']);
        $userId = $response['data']['id'];
        
        // Verify record was created
        $response = $this->get("/users/$userId", 
            ['Authorization' => "Bearer {$this->token}"]);
        $this->assertEquals('newuser@example.com', $response['data']['email']);
    }

    public function testCreateUserFailsValidation()
    {
        $response = $this->post('/users', [
            'email' => 'invalid-email',  // Invalid format
            'password' => 'short',        // Too short
            'first_name': 'J',            // Too short
            'last_name': 'D'              // Too short
        ], ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(400, $response['status']);
        // Error should mention all validation failures
        $this->assertContains('email', strtolower($response['error']));
        $this->assertContains('password', strtolower($response['error']));
    }

    public function testUpdatePartialRecord()
    {
        // Create user
        $createResp = $this->post('/users', [
            'email' => 'update-test@example.com',
            'password' => 'pass123456',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ], ['Authorization' => "Bearer {$this->token}"]);
        $userId = $createResp['data']['id'];
        
        // Update only first_name
        $response = $this->put("/users/$userId", [
            'first_name' => 'Jane'
        ], ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(200, $response['status']);
        
        // Verify only first_name changed
        $getResp = $this->get("/users/$userId", 
            ['Authorization' => "Bearer {$this->token}"]);
        $this->assertEquals('Jane', $getResp['data']['first_name']);
        $this->assertEquals('update-test@example.com', $getResp['data']['email']);
    }

    public function testDeleteWithBusinessRuleViolation()
    {
        // Try to delete current user (should fail per business rule)
        $response = $this->delete("/users/1",  // Assuming user ID 1 is current
            ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(403, $response['status']);
        $this->assertContains('cannot delete', strtolower($response['error']));
    }

    public function testListWithPagination()
    {
        $response = $this->get('/users?limit=10&offset=0', [
            'X-Pagination-Limit' => '10',
            'X-Pagination-Offset' => '0',
            'Authorization' => "Bearer {$this->token}"
        ]);
        
        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('pagination', $response['meta']);
        $this->assertEquals(10, $response['meta']['pagination']['limit']);
    }

    public function testSearchUsers()
    {
        $response = $this->get('/users', [
            'X-Search-Query' => 'john',
            'X-Search-Columns' => 'first_name,last_name',
            'X-Pagination-Limit' => '20',
            'Authorization' => "Bearer {$this->token}"
        ]);
        
        $this->assertEquals(200, $response['status']);
        // All results should match search
        foreach ($response['data'] as $user) {
            $fullName = strtolower($user['first_name'] . ' ' . $user['last_name']);
            $this->assertTrue(strpos($fullName, 'john') !== false);
        }
    }
}
```

### 3. Tree Operations Test

**File**: `Integration/TreeOperationsTest.php`

```php
<?php
class TreeOperationsTest extends TestCase
{
    public function testLoadCategoryTree()
    {
        $response = $this->get('/categories', 
            ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['data']);
        
        // Verify tree structure
        foreach ($response['data'] as $node) {
            $this->assertArrayHasKey('id', $node);
            $this->assertArrayHasKey('name', $node);
            $this->assertArrayHasKey('children', $node);
            $this->assertIsArray($node['children']);
        }
    }

    public function testSaveTreeStructure()
    {
        $treeData = [
            [
                'id' => '1',
                'name' => 'Electronics',
                'children' => [
                    [
                        'id' => '3',
                        'name' => 'Computers',
                        'children' => []
                    ]
                ]
            ]
        ];
        
        $response = $this->put('/categories', $treeData,
            ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(200, $response['status']);
        
        // Verify changes persisted
        $getResp = $this->get('/categories',
            ['Authorization' => "Bearer {$this->token}"]);
        // Tree should match what we saved
    }

    public function testCreateNewNodeInTree()
    {
        $treeData = [
            [
                'id' => '',  // Empty ID = new node
                'name' => 'New Category',
                'children' => []
            ]
        ];
        
        $response = $this->put('/categories', $treeData,
            ['Authorization' => "Bearer {$this->token}"]);
        
        $this->assertEquals(200, $response['status']);
        // New node should be created with assigned ID
    }
}
```

### 4. Error Handling Test

**File**: `Integration/ErrorHandlingTest.php`

```php
<?php
class ErrorHandlingTest extends TestCase
{
    public function testMissingAuthenticationToken()
    {
        $response = $this->get('/users');  // No Authorization header
        
        $this->assertEquals(401, $response['status']);
        $this->assertContains('logged in', strtolower($response['message']));
    }

    public function testInvalidToken()
    {
        $response = $this->get('/users', [
            'Authorization' => 'Bearer invalid.token.here'
        ]);
        
        $this->assertEquals(401, $response['status']);
    }

    public function testTableNotFound()
    {
        $response = $this->get('/nonexistent_table', [
            'Authorization' => "Bearer {$this->token}"
        ]);
        
        $this->assertEquals(404, $response['status']);
    }

    public function testSqlInjectionAttempt()
    {
        // Test handleForeignKeys is protected against SQL injection
        $response = $this->get('/items?foreignKeys=true&table=items&category_id=1 OR 1=1', [
            'Authorization' => "Bearer {$this->token}"
        ]);
        
        // Should safely handle injection attempt
        $this->assertNotNull($response['status']);
        // Should not return all items, only those matching category_id = 1
    }

    public function testResponseFormatConsistency()
    {
        $response = $this->get('/users', [
            'Authorization' => "Bearer {$this->token}"
        ]);
        
        // Every response must have these fields
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('error', $response);
    }
}
```

## Database Fixtures

### Test Database Setup

**File**: `tests/bootstrap.php`

```php
<?php
// Load .env.test
$dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env.test');
$dotenv->load();

// Create test database if not exists
$pdo = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD']
);
$pdo->exec('DROP DATABASE IF EXISTS ' . $_ENV['DB_NAME_TEST']);
$pdo->exec('CREATE DATABASE ' . $_ENV['DB_NAME_TEST']);

// Run schema
$schema = file_get_contents(__DIR__ . '/Fixtures/schema.sql');
$testDb = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME_TEST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD']
);
$testDb->exec($schema);

// Seed test data
$seeder = new DatabaseSeeder($testDb);
$seeder->seed();
```

### Base Test Case

**File**: `tests/Fixtures/TestCase.php`

```php
<?php
namespace SPLK2\Tests\Fixtures;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected PDO $db;
    protected static $api_url = 'http://localhost:8000';

    protected function setUp(): void
    {
        parent::setUp();
        // Connect to test database
        $this->db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME_TEST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD']
        );
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->db->exec('DELETE FROM users WHERE email LIKE "%@test.example.com"');
        parent::tearDown();
    }

    // HTTP Helper Methods
    protected function post(string $endpoint, array $data, array $headers = []): array
    {
        return $this->makeRequest('POST', $endpoint, $data, $headers);
    }

    protected function get(string $endpoint, array $headers = []): array
    {
        return $this->makeRequest('GET', $endpoint, null, $headers);
    }

    protected function put(string $endpoint, array $data, array $headers = []): array
    {
        return $this->makeRequest('PUT', $endpoint, $data, $headers);
    }

    protected function delete(string $endpoint, array $headers = []): array
    {
        return $this->makeRequest('DELETE', $endpoint, null, $headers);
    }

    private function makeRequest(string $method, string $endpoint, ?array $data, array $headers): array
    {
        $ch = curl_init(self::$api_url . $endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->buildHeaders($headers),
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }

    private function buildHeaders(array $custom): array
    {
        $headers = [
            'Content-Type: application/json',
        ];
        return array_merge($headers, array_map(fn($k, $v) => "$k: $v", 
            array_keys($custom), $custom));
    }

    protected function isValidJWT(string $token): bool
    {
        // Validate JWT format and signature
        return substr_count($token, '.') === 2;
    }
}
```

## Running Tests

### Run All Tests
```bash
cd /api
./vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# Unit tests only
./vendor/bin/phpunit tests/Unit/

# Integration tests only
./vendor/bin/phpunit tests/Integration/
```

### Run With Coverage Report
```bash
./vendor/bin/phpunit --coverage-html coverage/
# Open coverage/index.html in browser
```

### Watch Mode (auto-run on file change)
```bash
# Requires additional tools: composer require --dev phpunit-watcher/phpunit-watcher
./vendor/bin/phpunit-watcher watch
```

## Continuous Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: splk_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Install Dependencies
        run: composer install
      
      - name: Run Tests
        run: ./vendor/bin/phpunit
        env:
          DB_HOST: localhost
          DB_USER: root
          DB_PASSWORD: root
          DB_NAME_TEST: splk_test
```

## Test Execution Checklist

Before merging Rules system:

- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Code coverage >= 85%
- [ ] No SQL injection vulnerabilities detected
- [ ] Auth flow tested (login, register, token)
- [ ] Validation rules tested for all tables
- [ ] Business logic hooks tested
- [ ] Tree operations atomic
- [ ] Error responses consistent
- [ ] Performance acceptable (< 500ms per request)

## Coverage Goals

| Component | Goal | Priority |
|-----------|------|----------|
| RuleValidator | 90% | Critical |
| Validation Logic | 85% | Critical |
| CRUD Operations | 80% | High |
| Auth Flow | 95% | Critical |
| Error Handling | 75% | Medium |
| Tree Operations | 70% | Medium |

