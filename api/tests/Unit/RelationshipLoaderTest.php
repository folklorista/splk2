<?php
namespace Tests\Unit;

use App\RelationshipLoader;
use App\Database;
use App\Logger;
use PHPUnit\Framework\TestCase;

class RelationshipLoaderTest extends TestCase
{
    private RelationshipLoader $loader;
    private Database $db;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->logger = $this->createMock(Logger::class);
        $this->loader = new RelationshipLoader($this->db, $this->logger);
    }

    /**
     * Test parsing valid include parameter
     */
    public function testParseIncludeValid()
    {
        $relationships = $this->loader->parseInclude('roles,groups,events');
        $this->assertEquals(['roles', 'groups', 'events'], $relationships);
    }

    /**
     * Test parsing include with whitespace
     */
    public function testParseIncludeWithWhitespace()
    {
        $relationships = $this->loader->parseInclude('roles, groups , events');
        $this->assertEquals(['roles', 'groups', 'events'], $relationships);
    }

    /**
     * Test parsing single include
     */
    public function testParseIncludeSingle()
    {
        $relationships = $this->loader->parseInclude('roles');
        $this->assertEquals(['roles'], $relationships);
    }

    /**
     * Test parsing empty include
     */
    public function testParseIncludeEmpty()
    {
        $this->assertEmpty($this->loader->parseInclude(null));
        $this->assertEmpty($this->loader->parseInclude(''));
        $this->assertEmpty($this->loader->parseInclude('   '));
    }

    /**
     * Test parsing invalid relationship name
     */
    public function testParseIncludeInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->loader->parseInclude('roles;drop,groups');
    }

    /**
     * Test parsing relationship with numbers
     */
    public function testParseIncludeWithNumbers()
    {
        $relationships = $this->loader->parseInclude('group1,role2,item_3');
        $this->assertEquals(['group1', 'role2', 'item_3'], $relationships);
    }

    /**
     * Test invalid characters in relationship name
     */
    public function testParseIncludeInvalidChars()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->loader->parseInclude('roles-groups');
    }


    /**
     * Test loading relationships with empty relationship list
     */
    public function testLoadRelationshipsEmpty()
    {
        $record = [
            'id' => 1,
            'name' => 'John',
            'role_id' => 2
        ];

        $result = $this->loader->loadRelationships('users', $record, []);

        $this->assertEquals($record, $result);
    }

    /**
     * Test loading relationships with empty record
     */
    public function testLoadRelationshipsEmptyRecord()
    {
        $result = $this->loader->loadRelationships('users', [], ['role']);
        $this->assertEmpty($result);
    }

    /**
     * Test loading relationships handles errors gracefully
     */
    public function testLoadRelationshipsHandlesErrors()
    {
        // Mock database to throw exception
        $this->db->method('get')
            ->willThrowException(new \Exception('Database error'));

        $record = [
            'id' => 1,
            'name' => 'John',
            'role_id' => 2
        ];

        // Should not throw, just log warning
        $result = $this->loader->loadRelationships('users', $record, ['roles']);

        // Original record returned without relationship
        $this->assertEquals($record, $result);
        $this->assertArrayNotHasKey('roles', $result);
    }


    /**
     * Test that non-existent FK is skipped
     */
    public function testLoadRelationshipsSkipsNonExistentFK()
    {
        $record = [
            'id' => 1,
            'name' => 'John',
            'role_id' => null
        ];

        $result = $this->loader->loadRelationships('users', $record, ['roles']);

        // Should still have original record
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John', $result['name']);
        // But no role loaded (FK was null)
        $this->assertArrayNotHasKey('role', $result);
    }

    /**
     * Test parsing include with trailing commas
     */
    public function testParseIncludeTrailingCommas()
    {
        $relationships = $this->loader->parseInclude('roles,groups,');
        $this->assertEquals(['roles', 'groups'], $relationships);
    }


    /**
     * Test that invalid relationship name throws exception
     */
    public function testParseIncludeStartWithNumber()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->loader->parseInclude('1roles,groups');
    }
}
