<?php
namespace SPLK2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\RuleValidator;
use App\RuleException;

/**
 * Unit Tests for RuleValidator
 *
 * Tests validation constraints, hooks, and error handling
 */
class RuleValidatorTest extends TestCase
{
    private RuleValidator $validator;
    private \App\Database $db;
    private \App\Logger $logger;

    protected function setUp(): void
    {
        // Mock database and logger
        $this->db = $this->createMock(\App\Database::class);
        $this->logger = $this->createMock(\App\Logger::class);

        // Create validator with test rules
        $rules = $this->getTestRules();
        $this->validator = new RuleValidator($rules, $this->db, $this->logger);
    }

    /**
     * Get test rules for unit tests
     */
    private function getTestRules(): array
    {
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
                    'age' => [
                        'type' => 'integer',
                        'min' => 18,
                        'max' => 120,
                    ],
                    'role' => [
                        'type' => 'string',
                        'enum' => ['admin', 'user', 'guest'],
                    ],
                ],
                'hooks' => [
                    'beforeCreate' => function($data, $user, $logger) {
                        if (isset($data['email']) && strpos($data['email'], 'admin') !== false) {
                            throw new RuleException('Cannot create admin user this way', 403, 'users', 'beforeCreate');
                        }
                    },
                    'beforeDelete' => function($id, $user, $logger, $db) {
                        if ($id === 1) {
                            throw new RuleException('Cannot delete system admin', 403, 'users', 'beforeDelete');
                        }
                    },
                ],
            ],
            'items' => [
                'validation' => [
                    'name' => [
                        'type' => 'string',
                        'required' => true,
                        'minLength' => 3,
                        'maxLength' => 100,
                    ],
                    'description' => [
                        'type' => 'string',
                        'maxLength' => 500,
                    ],
                    'quantity' => [
                        'type' => 'integer',
                        'min' => 0,
                        'max' => 10000,
                    ],
                    'active' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
            'products' => [
                'validation' => [
                    'code' => [
                        'type' => 'string',
                        'required' => true,
                        'minLength' => 5,
                        'maxLength' => 20,
                        'unique' => true,
                    ],
                    'price' => [
                        'type' => 'float',
                        'required' => true,
                        'min' => 0.01,
                    ],
                ],
            ],
        ];
    }

    // ============= REQUIRED FIELD VALIDATION =============

    /**
     * Test required field is present
     */
    public function test_required_field_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('errors', $result);
    }

    /**
     * Test required field is missing
     */
    public function test_required_field_missing(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            // password is missing
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertContains('password is required', $result['errors']);
    }

    /**
     * Test required field is empty string
     */
    public function test_required_field_empty_string(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => '',
            'password' => 'SecurePass123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('email is required', $result['errors']);
    }

    /**
     * Test required field is null
     */
    public function test_required_field_null(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => null,
            'password' => 'SecurePass123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('email is required', $result['errors']);
    }

    // ============= TYPE VALIDATION =============

    /**
     * Test email type validation - valid
     */
    public function test_email_type_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'valid@example.com',
            'password' => 'SecurePass123',
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test email type validation - invalid
     */
    public function test_email_type_invalid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'not-an-email',
            'password' => 'SecurePass123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('email must be of type email', $result['errors']);
    }

    /**
     * Test integer type validation - valid
     */
    public function test_integer_type_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => 25,
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test integer type validation - string integer
     */
    public function test_integer_type_string_integer(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => '25',
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test integer type validation - float (invalid)
     */
    public function test_integer_type_float_invalid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => 25.5,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('age must be of type integer', $result['errors']);
    }

    /**
     * Test float type validation - valid
     */
    public function test_float_type_valid(): void
    {
        $result = $this->validator->validateCreate('products', [
            'code' => 'PROD001',
            'price' => 99.99,
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test boolean type validation
     */
    public function test_boolean_type_valid(): void
    {
        $result = $this->validator->validateCreate('items', [
            'name' => 'Test Item',
            'active' => true,
        ]);

        $this->assertTrue($result['valid']);

        // Also test with 1/0
        $result = $this->validator->validateCreate('items', [
            'name' => 'Test Item',
            'active' => 1,
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test string type validation
     */
    public function test_string_type_valid(): void
    {
        $result = $this->validator->validateCreate('items', [
            'name' => 'Test Item',
            'description' => 'A test item',
        ]);

        $this->assertTrue($result['valid']);
    }

    // ============= LENGTH VALIDATION =============

    /**
     * Test minLength constraint - valid
     */
    public function test_minLength_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',  // >= 8 chars
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test minLength constraint - too short
     */
    public function test_minLength_too_short(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'short',  // < 8 chars
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('password must be at least 8 characters', $result['errors']);
    }

    /**
     * Test maxLength constraint - valid
     */
    public function test_maxLength_valid(): void
    {
        $result = $this->validator->validateCreate('items', [
            'name' => 'Valid Name',
            'description' => str_repeat('x', 500),  // exactly 500
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test maxLength constraint - too long
     */
    public function test_maxLength_too_long(): void
    {
        $result = $this->validator->validateCreate('items', [
            'name' => 'Valid Name',
            'description' => str_repeat('x', 501),  // > 500
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('description must be at most 500 characters', $result['errors']);
    }

    // ============= ENUM VALIDATION =============

    /**
     * Test enum constraint - valid value
     */
    public function test_enum_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'role' => 'admin',
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test enum constraint - invalid value
     */
    public function test_enum_invalid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'role' => 'superuser',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('role must be one of: admin, user, guest', $result['errors']);
    }

    // ============= MIN/MAX VALUE VALIDATION =============

    /**
     * Test min constraint - valid
     */
    public function test_min_value_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => 18,
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test min constraint - too low
     */
    public function test_min_value_too_low(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => 17,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('age must be at least 18', $result['errors']);
    }

    /**
     * Test max constraint - valid
     */
    public function test_max_value_valid(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => 120,
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test max constraint - too high
     */
    public function test_max_value_too_high(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123',
            'age' => 121,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('age must be at most 120', $result['errors']);
    }

    /**
     * Test min constraint for float - valid
     */
    public function test_min_float_valid(): void
    {
        $result = $this->validator->validateCreate('products', [
            'code' => 'PROD001',
            'price' => 0.01,
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test min constraint for float - too low
     */
    public function test_min_float_too_low(): void
    {
        $result = $this->validator->validateCreate('products', [
            'code' => 'PROD001',
            'price' => 0.00,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('price must be at least 0.01', $result['errors']);
    }

    // ============= UNIQUE VALIDATION =============

    /**
     * Test unique constraint - value exists
     */
    public function test_unique_exists(): void
    {
        // Mock database to return existing email
        $this->db->expects($this->once())
            ->method('getAllWhere')
            ->with('users', '`email` = :value', [':value' => 'existing@example.com'])
            ->willReturn(['data' => [['email' => 'existing@example.com']]]);

        $result = $this->validator->validateCreate('users', [
            'email' => 'existing@example.com',
            'password' => 'SecurePass123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('email already exists', $result['errors']);
    }

    /**
     * Test unique constraint - value doesn't exist
     */
    public function test_unique_not_exists(): void
    {
        // Mock database to return no results
        $this->db->expects($this->once())
            ->method('getAllWhere')
            ->with('users', '`email` = :value', [':value' => 'new@example.com'])
            ->willReturn(['data' => []]);

        $result = $this->validator->validateCreate('users', [
            'email' => 'new@example.com',
            'password' => 'SecurePass123',
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test unique constraint - database returns 404
     */
    public function test_unique_database_404(): void
    {
        // Mock database to return 404
        $this->db->expects($this->once())
            ->method('getAllWhere')
            ->willReturn(['status' => 404]);

        $result = $this->validator->validateCreate('users', [
            'email' => 'new@example.com',
            'password' => 'SecurePass123',
        ]);

        $this->assertTrue($result['valid']);
    }

    // ============= UPDATE VALIDATION =============

    /**
     * Test UPDATE doesn't require all fields
     */
    public function test_update_partial_data(): void
    {
        $result = $this->validator->validateUpdate('users', [
            'age' => 30,
            // email and password not provided
        ]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test UPDATE validates provided field
     */
    public function test_update_validates_provided(): void
    {
        $result = $this->validator->validateUpdate('users', [
            'email' => 'invalid-email',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('email must be of type email', $result['errors']);
    }

    // ============= HOOK EXECUTION =============

    /**
     * Test beforeCreate hook is executed
     */
    public function test_hook_beforeCreate_executed(): void
    {
        $this->expectException(RuleException::class);
        $this->expectExceptionMessage('Cannot create admin user this way');

        $this->validator->executeHook('users', 'beforeCreate', ['email' => 'admin@example.com'], null, $this->logger);
    }

    /**
     * Test beforeDelete hook is executed
     */
    public function test_hook_beforeDelete_executed(): void
    {
        $this->expectException(RuleException::class);
        $this->expectExceptionMessage('Cannot delete system admin');

        $this->validator->executeHook('users', 'beforeDelete', 1, null, $this->logger, $this->db);
    }

    /**
     * Test hook with no matching table
     */
    public function test_hook_no_matching_table(): void
    {
        // Should not throw, just return silently
        $this->validator->executeHook('nonexistent', 'beforeCreate', [], null, $this->logger);
        $this->assertTrue(true);
    }

    /**
     * Test hook with no matching hook name
     */
    public function test_hook_no_matching_hook(): void
    {
        // Should not throw, just return silently
        $this->validator->executeHook('users', 'nonexistent', [], null, $this->logger);
        $this->assertTrue(true);
    }

    // ============= TABLE RULES INFO =============

    /**
     * Test getTableRules returns rules for table
     */
    public function test_getTableRules(): void
    {
        $rules = $this->validator->getTableRules('users');

        $this->assertNotNull($rules);
        $this->assertArrayHasKey('validation', $rules);
        $this->assertArrayHasKey('hooks', $rules);
    }

    /**
     * Test getTableRules returns null for nonexistent table
     */
    public function test_getTableRules_nonexistent(): void
    {
        $rules = $this->validator->getTableRules('nonexistent');

        $this->assertNull($rules);
    }

    /**
     * Test hasRules returns true for table with rules
     */
    public function test_hasRules_true(): void
    {
        $this->assertTrue($this->validator->hasRules('users'));
    }

    /**
     * Test hasRules returns false for table without rules
     */
    public function test_hasRules_false(): void
    {
        $this->assertFalse($this->validator->hasRules('nonexistent'));
    }

    // ============= ERROR HANDLING =============

    /**
     * Test multiple validation errors are collected
     */
    public function test_multiple_errors(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'invalid-email',
            'password' => 'short',
            'age' => 150,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertCount(3, $result['errors']);
        $this->assertContains('email must be of type email', $result['errors']);
        $this->assertContains('password must be at least 8 characters', $result['errors']);
        $this->assertContains('age must be at most 120', $result['errors']);
    }

    /**
     * Test validation stops at required field failure
     */
    public function test_required_stops_other_validations(): void
    {
        $result = $this->validator->validateCreate('users', [
            'email' => 'valid@example.com',
            'password' => '',  // required but empty
            'age' => 'invalid',  // also invalid type
        ]);

        $this->assertFalse($result['valid']);
        // Should only have password error, not age error
        $this->assertContains('password is required', $result['errors']);
    }

    /**
     * Test table without rules passes validation
     */
    public function test_no_rules_for_table(): void
    {
        $result = $this->validator->validateCreate('unknown_table', [
            'any_field' => 'any_value',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('errors', $result);
    }
}
