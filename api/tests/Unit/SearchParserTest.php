<?php
namespace Tests\Unit;

use App\SearchParser;
use PHPUnit\Framework\TestCase;

class SearchParserTest extends TestCase
{
    public function testParseEmptyOrNull()
    {
        $this->assertNull(SearchParser::parse(null));
        $this->assertNull(SearchParser::parse(''));
        $this->assertNull(SearchParser::parse('   '));
    }

    public function testParseSingleShortTermStaysExact()
    {
        $result = SearchParser::parse('id');
        $this->assertEquals('id', $result['boolean_query']);
        $this->assertEquals(['id'], $result['terms']);
    }

    public function testParsePlainTermsGetAutoWildcard()
    {
        $result = SearchParser::parse('john doe');
        $this->assertEquals('john* doe*', $result['boolean_query']);
        $this->assertEquals(['john', 'doe'], $result['terms']);
    }

    public function testParseAndOperatorRequiresBothTerms()
    {
        $result = SearchParser::parse('john AND doe');
        $this->assertEquals('+john* +doe*', $result['boolean_query']);
    }

    public function testParseOrOperatorIsNoOp()
    {
        $result = SearchParser::parse('john OR jane');
        $this->assertEquals('john* jane*', $result['boolean_query']);
    }

    public function testParseNotKeywordExcludesTerm()
    {
        $result = SearchParser::parse('john NOT admin');
        $this->assertEquals('john* -admin', $result['boolean_query']);
        $this->assertEquals(['john'], $result['terms']);
    }

    public function testParseLeadingMinusExcludesTerm()
    {
        $result = SearchParser::parse('john -admin');
        $this->assertEquals('john* -admin', $result['boolean_query']);
    }

    public function testParseQuotedPhraseKeepsExactMatch()
    {
        $result = SearchParser::parse('"john doe"');
        $this->assertEquals('"john doe"', $result['boolean_query']);
        $this->assertEquals(['john doe'], $result['terms']);
    }

    public function testParseExplicitWildcardIsPreserved()
    {
        $result = SearchParser::parse('jo*');
        $this->assertEquals('jo*', $result['boolean_query']);
    }

    public function testParseStripsUnsafeCharacters()
    {
        $result = SearchParser::parse('john; DROP TABLE users');
        $this->assertStringNotContainsString(';', $result['boolean_query']);
        $this->assertStringNotContainsString('--', $result['boolean_query']);
    }

    public function testParseDuplicateTermsAreDeduplicated()
    {
        $result = SearchParser::parse('john john');
        $this->assertEquals(['john'], $result['terms']);
    }
}
