<?php
namespace Tests\Unit;

use App\SortParser;
use PHPUnit\Framework\TestCase;

class SortParserTest extends TestCase
{
    public function testParseSingleColumnAscending()
    {
        $sort = SortParser::parse('last_name');
        $this->assertEquals([['column' => 'last_name', 'direction' => 'ASC']], $sort);
    }

    public function testParseSingleColumnDescending()
    {
        $sort = SortParser::parse('-created_at');
        $this->assertEquals([['column' => 'created_at', 'direction' => 'DESC']], $sort);
    }

    public function testParseExplicitAscending()
    {
        $sort = SortParser::parse('+first_name');
        $this->assertEquals([['column' => 'first_name', 'direction' => 'ASC']], $sort);
    }

    public function testParseMultipleColumns()
    {
        $sort = SortParser::parse('last_name,-first_name');
        $this->assertEquals([
            ['column' => 'last_name', 'direction' => 'ASC'],
            ['column' => 'first_name', 'direction' => 'DESC'],
        ], $sort);
    }

    public function testParseWithWhitespace()
    {
        $sort = SortParser::parse(' last_name , -first_name ');
        $this->assertEquals([
            ['column' => 'last_name', 'direction' => 'ASC'],
            ['column' => 'first_name', 'direction' => 'DESC'],
        ], $sort);
    }

    public function testParseEmptyOrNull()
    {
        $this->assertNull(SortParser::parse(null));
        $this->assertNull(SortParser::parse(''));
        $this->assertNull(SortParser::parse('   '));
        $this->assertNull(SortParser::parse('  ,  ,  '));
    }

    public function testParseInvalidColumnName()
    {
        $this->expectException(\InvalidArgumentException::class);
        SortParser::parse('id;DROP TABLE users;--');
    }

    public function testParseInvalidColumnStartingWithDigit()
    {
        $this->expectException(\InvalidArgumentException::class);
        SortParser::parse('1column');
    }
}
