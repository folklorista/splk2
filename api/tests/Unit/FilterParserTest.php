<?php
namespace Tests\Unit;

use App\FilterParser;
use App\WhereClauseBuilder;
use PHPUnit\Framework\TestCase;

class FilterParserTest extends TestCase
{
    private array $columns = ['id', 'age', 'status', 'name'];

    public function testParseEmptyReturnsEmptyArray()
    {
        $this->assertEquals([], FilterParser::parse(null, $this->columns));
        $this->assertEquals([], FilterParser::parse([], $this->columns));
    }

    public function testParseShorthandEqualityFilter()
    {
        $conditions = FilterParser::parse(['status' => 'active'], $this->columns);
        $this->assertEquals([['column' => 'status', 'operator' => '=', 'value' => 'active']], $conditions);
    }

    public function testParseOperatorFilter()
    {
        $conditions = FilterParser::parse(['age' => ['gte' => '18']], $this->columns);
        $this->assertEquals([['column' => 'age', 'operator' => '>=', 'value' => '18']], $conditions);
    }

    public function testParseMultipleOperatorsOnSameColumn()
    {
        $conditions = FilterParser::parse(['age' => ['gte' => '18', 'lte' => '65']], $this->columns);
        $this->assertCount(2, $conditions);
    }

    public function testParseInFilterSplitsCommaSeparatedValues()
    {
        $conditions = FilterParser::parse(['status' => ['in' => 'active,pending']], $this->columns);
        $this->assertEquals(['active', 'pending'], $conditions[0]['value']);
        $this->assertEquals('IN', $conditions[0]['operator']);
    }

    public function testParseLikeFilterWrapsValueInWildcards()
    {
        $conditions = FilterParser::parse(['name' => ['like' => 'smith']], $this->columns);
        $this->assertEquals('%smith%', $conditions[0]['value']);
    }

    public function testParseRejectsUnknownColumn()
    {
        $this->expectException(\InvalidArgumentException::class);
        FilterParser::parse(['secret_column' => 'x'], $this->columns);
    }

    public function testParseRejectsUnknownOperator()
    {
        $this->expectException(\InvalidArgumentException::class);
        FilterParser::parse(['age' => ['bogus' => '1']], $this->columns);
    }

    public function testApplyBuildsWhereClauseWithBoundParams()
    {
        $conditions = FilterParser::parse(['age' => ['gte' => '18'], 'status' => 'active'], $this->columns);
        $builder = new WhereClauseBuilder();
        FilterParser::apply($builder, $conditions);

        $this->assertEquals(2, $builder->count());
        $this->assertStringContainsString('`age` >=', $builder->build());
        $this->assertStringContainsString('`status` =', $builder->build());
        $this->assertEqualsCanonicalizing(['18', 'active'], array_values($builder->getParams()));
    }
}
