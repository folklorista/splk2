<?php
namespace Tests;

use App\FieldSelector;
use PHPUnit\Framework\TestCase;

class FieldSelectorTest extends TestCase
{
    /**
     * Test parsing field selection from query parameter
     */
    public function testParseFieldsValid()
    {
        // Basic field list
        $fields = FieldSelector::parseFields('id,name,email');
        $this->assertEquals(['id', 'name', 'email'], $fields);

        // With whitespace
        $fields = FieldSelector::parseFields('id, name, email');
        $this->assertEquals(['id', 'name', 'email'], $fields);

        // Single field
        $fields = FieldSelector::parseFields('id');
        $this->assertEquals(['id'], $fields);

        // With underscores
        $fields = FieldSelector::parseFields('user_id,created_at,last_modified');
        $this->assertEquals(['user_id', 'created_at', 'last_modified'], $fields);
    }

    /**
     * Test parsing invalid field names
     */
    public function testParseFieldsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSelector::parseFields('id,123invalid,name');
    }

    /**
     * Test parsing invalid field names with special chars
     */
    public function testParseFieldsInvalidSpecialChars()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSelector::parseFields('id,name;drop,email');
    }

    /**
     * Test parsing empty or null fields
     */
    public function testParseFieldsEmpty()
    {
        $this->assertNull(FieldSelector::parseFields(null));
        $this->assertNull(FieldSelector::parseFields(''));
        $this->assertNull(FieldSelector::parseFields('   '));
    }

    /**
     * Test always protected fields (crypto keys only)
     */
    public function testIsAlwaysProtected()
    {
        $this->assertTrue(FieldSelector::isAlwaysProtected('api_secret'));
        $this->assertTrue(FieldSelector::isAlwaysProtected('API_SECRET'));
        $this->assertTrue(FieldSelector::isAlwaysProtected('jwt_secret'));
        $this->assertTrue(FieldSelector::isAlwaysProtected('private_key'));

        // password/token fields are NOT always protected - they're table-specific
        $this->assertFalse(FieldSelector::isAlwaysProtected('password'));
        $this->assertFalse(FieldSelector::isAlwaysProtected('token'));
        $this->assertFalse(FieldSelector::isAlwaysProtected('email'));
        $this->assertFalse(FieldSelector::isAlwaysProtected('name'));
        $this->assertFalse(FieldSelector::isAlwaysProtected('id'));
    }

    /**
     * Test sensitive fields per table
     */
    public function testIsSensitiveForTable()
    {
        // Users table
        $this->assertTrue(FieldSelector::isSensitiveForTable('users', 'password'));
        $this->assertTrue(FieldSelector::isSensitiveForTable('users', 'password_hash'));
        $this->assertFalse(FieldSelector::isSensitiveForTable('users', 'email'));
        $this->assertFalse(FieldSelector::isSensitiveForTable('users', 'name'));

        // API keys table
        $this->assertTrue(FieldSelector::isSensitiveForTable('api_keys', 'secret'));
        $this->assertFalse(FieldSelector::isSensitiveForTable('api_keys', 'name'));

        // Refresh tokens table
        $this->assertTrue(FieldSelector::isSensitiveForTable('refresh_tokens', 'token_hash'));
        $this->assertFalse(FieldSelector::isSensitiveForTable('refresh_tokens', 'user_id'));

        // Non-configured table
        $this->assertFalse(FieldSelector::isSensitiveForTable('items', 'password'));
    }

    /**
     * Test should include field logic
     */
    public function testShouldIncludeFieldNoRequest()
    {
        // When no fields requested, return non-sensitive fields
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'id', null));
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'email', null));
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'name', null));

        // Sensitive fields excluded when not requested
        $this->assertFalse(FieldSelector::shouldIncludeField('users', 'password', null));
        $this->assertFalse(FieldSelector::shouldIncludeField('users', 'password_hash', null));

        // Always protected fields always excluded
        $this->assertFalse(FieldSelector::shouldIncludeField('api_keys', 'api_secret', null));
    }

    /**
     * Test should include field when explicitly requested
     */
    public function testShouldIncludeFieldExplicitRequest()
    {
        // Note: password fields are in SENSITIVE_BY_TABLE for users table
        // These CAN be included when explicitly requested
        $requestedFields = ['id', 'email', 'password'];

        // Requested fields are included
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'id', $requestedFields));
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'email', $requestedFields));
        // Password is sensitive but can be included if explicitly requested
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'password', $requestedFields));

        // Non-requested fields are excluded
        $this->assertFalse(FieldSelector::shouldIncludeField('users', 'name', $requestedFields));

        // Always protected fields are NEVER included, even if explicitly requested
        $this->assertFalse(FieldSelector::shouldIncludeField('api_keys', 'api_secret', ['id', 'api_secret']));
        $this->assertFalse(FieldSelector::shouldIncludeField('api_keys', 'api_key', ['id', 'api_key']));
    }

    /**
     * Test field name case insensitivity
     */
    public function testFieldNameCaseInsensitivity()
    {
        $requestedFields = ['ID', 'EMAIL'];

        // Case insensitive matching
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'id', $requestedFields));
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'ID', $requestedFields));
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'Id', $requestedFields));
        $this->assertTrue(FieldSelector::shouldIncludeField('users', 'email', $requestedFields));
    }

    /**
     * Test filtering a single record
     */
    public function testFilterRecord()
    {
        $record = [
            'id' => 1,
            'email' => 'user@example.com',
            'name' => 'John Doe',
            'password' => 'hashed_password',
            'password_hash' => 'bcrypt_hash',
            'created_at' => '2023-01-01',
        ];

        // No field specification - should exclude sensitive fields
        $filtered = FieldSelector::filterRecord($record, 'users', null);
        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayHasKey('created_at', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
        $this->assertArrayNotHasKey('password_hash', $filtered);

        // Specific fields requested - includes password if explicitly requested
        $filtered = FieldSelector::filterRecord($record, 'users', ['id', 'email', 'password']);
        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayHasKey('password', $filtered);
        $this->assertArrayNotHasKey('name', $filtered);

        // Request only id and email
        $filtered = FieldSelector::filterRecord($record, 'users', ['id', 'email']);
        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
    }

    /**
     * Test filtering multiple records
     */
    public function testFilterRecords()
    {
        $records = [
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'John Doe',
                'password' => 'hashed1',
            ],
            [
                'id' => 2,
                'email' => 'user2@example.com',
                'name' => 'Jane Smith',
                'password' => 'hashed2',
            ],
        ];

        $filtered = FieldSelector::filterRecords($records, 'users', null);

        // All records should be filtered
        $this->assertCount(2, $filtered);

        // Each record should have non-sensitive fields
        foreach ($filtered as $record) {
            $this->assertArrayHasKey('id', $record);
            $this->assertArrayHasKey('email', $record);
            $this->assertArrayHasKey('name', $record);
            $this->assertArrayNotHasKey('password', $record);
        }
    }

    /**
     * Test getting filtered fields
     */
    public function testGetFilteredFields()
    {
        $original = ['id' => 1, 'email' => 'test@test.com', 'password' => 'secret', 'name' => 'Test'];
        $filtered = ['id' => 1, 'email' => 'test@test.com', 'name' => 'Test'];

        $removed = FieldSelector::getFilteredFields($original, $filtered);

        $this->assertContains('password', $removed);
        $this->assertCount(1, $removed);
    }

    /**
     * Test getting default visible fields
     */
    public function testGetDefaultVisibleFields()
    {
        $record = [
            'id' => 1,
            'email' => 'user@example.com',
            'name' => 'John',
            'password' => 'secret',
            'created_at' => '2023-01-01',
        ];

        $visible = FieldSelector::getDefaultVisibleFields('users', $record);

        $this->assertContains('id', $visible);
        $this->assertContains('email', $visible);
        $this->assertContains('name', $visible);
        $this->assertContains('created_at', $visible);
        $this->assertNotContains('password', $visible);
    }

    /**
     * Test SQL injection attempts in field names
     */
    public function testSQLInjectionPrevention()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSelector::parseFields("id; DROP TABLE users;--");
    }

    /**
     * Test XSS prevention in field names
     */
    public function testXSSPrevention()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSelector::parseFields("id,<script>alert('xss')</script>");
    }

    /**
     * Test filter with non-existent fields in request
     */
    public function testFilterWithNonExistentFields()
    {
        $record = ['id' => 1, 'email' => 'test@test.com', 'name' => 'Test'];
        $filtered = FieldSelector::filterRecord($record, 'users', ['id', 'nonexistent', 'email']);

        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('nonexistent', $filtered);
        $this->assertArrayNotHasKey('name', $filtered);
    }

    /**
     * Test case insensitive table names in sensitive field checking
     */
    public function testTableNameCaseInsensitivity()
    {
        $record = ['id' => 1, 'password' => 'secret'];

        $filtered1 = FieldSelector::filterRecord($record, 'users', null);
        $filtered2 = FieldSelector::filterRecord($record, 'USERS', null);
        $filtered3 = FieldSelector::filterRecord($record, 'Users', null);

        // All should produce same result
        $this->assertEquals(array_keys($filtered1), array_keys($filtered2));
        $this->assertEquals(array_keys($filtered2), array_keys($filtered3));
    }

    /**
     * Test empty field list after parsing
     */
    public function testParseFieldsEmptyAfterTrim()
    {
        $this->assertNull(FieldSelector::parseFields('   ,   ,   '));
    }
}
