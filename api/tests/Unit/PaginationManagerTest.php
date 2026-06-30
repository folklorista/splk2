<?php
namespace App\Tests;

use App\PaginationManager;
use PHPUnit\Framework\TestCase;

class PaginationManagerTest extends TestCase
{
    /**
     * Test default pagination values
     */
    public function testDefaultPagination()
    {
        $result = PaginationManager::parse(null, null);

        $this->assertEquals(PaginationManager::DEFAULT_LIMIT, $result['limit']);
        $this->assertEquals(0, $result['offset']);
    }

    /**
     * Test parsing with provided limit and offset
     */
    public function testParseWithLimitAndOffset()
    {
        $result = PaginationManager::parse(50, 100);

        $this->assertEquals(50, $result['limit']);
        $this->assertEquals(100, $result['offset']);
    }

    /**
     * Test limit enforcement - too high
     */
    public function testLimitEnforcementTooHigh()
    {
        $result = PaginationManager::parse(999999, 0);

        $this->assertEquals(PaginationManager::MAX_LIMIT, $result['limit']);
    }

    /**
     * Test limit enforcement - too low
     */
    public function testLimitEnforcementTooLow()
    {
        $result = PaginationManager::parse(0, 0);

        $this->assertEquals(PaginationManager::DEFAULT_LIMIT, $result['limit']);
    }

    /**
     * Test negative limit defaults to default
     */
    public function testNegativeLimitDefaultsToDefault()
    {
        $result = PaginationManager::parse(-50, 0);

        $this->assertEquals(PaginationManager::DEFAULT_LIMIT, $result['limit']);
    }

    /**
     * Test offset enforcement - negative
     */
    public function testNegativeOffsetDefaultsToZero()
    {
        $result = PaginationManager::parse(10, -50);

        $this->assertEquals(0, $result['offset']);
    }

    /**
     * Test maximum offset enforcement
     */
    public function testMaximumOffsetEnforcement()
    {
        $result = PaginationManager::parse(10, 9999999999);

        $this->assertEquals(PaginationManager::MAX_OFFSET, $result['offset']);
    }

    /**
     * Test building metadata with standard pagination
     */
    public function testBuildMetadataStandard()
    {
        $metadata = PaginationManager::buildMetadata(1000, 100, 0);

        $this->assertEquals(1000, $metadata['pagination']['total']);
        $this->assertEquals(100, $metadata['pagination']['limit']);
        $this->assertEquals(0, $metadata['pagination']['offset']);
        $this->assertEquals(100, $metadata['pagination']['returned']);
        $this->assertTrue($metadata['pagination']['has_more']);
        $this->assertEquals(1, $metadata['pagination']['page']);
        $this->assertEquals(10, $metadata['pagination']['pages']);
    }

    /**
     * Test metadata on last page
     */
    public function testBuildMetadataLastPage()
    {
        $metadata = PaginationManager::buildMetadata(1000, 100, 900);

        $this->assertEquals(100, $metadata['pagination']['returned']);
        $this->assertFalse($metadata['pagination']['has_more']);
        $this->assertEquals(10, $metadata['pagination']['page']);
    }

    /**
     * Test metadata on partial last page
     */
    public function testBuildMetadataPartialLastPage()
    {
        $metadata = PaginationManager::buildMetadata(1050, 100, 900);

        // With offset 900, limit 100, total 1050: returns 100 records (records 900-999)
        // Page 10 shows records 900-999, page 11 shows records 1000-1049 (50 records)
        $this->assertEquals(100, $metadata['pagination']['returned']);
        $this->assertTrue($metadata['pagination']['has_more']);  // More on page 11
        $this->assertEquals(11, $metadata['pagination']['pages']);
    }

    /**
     * Test metadata when total is less than limit
     */
    public function testBuildMetadataTotalLessThanLimit()
    {
        $metadata = PaginationManager::buildMetadata(50, 100, 0);

        $this->assertEquals(50, $metadata['pagination']['returned']);
        $this->assertFalse($metadata['pagination']['has_more']);
        $this->assertEquals(1, $metadata['pagination']['pages']);
    }

    /**
     * Test metadata when total is zero
     */
    public function testBuildMetadataZeroTotal()
    {
        $metadata = PaginationManager::buildMetadata(0, 100, 0);

        $this->assertEquals(0, $metadata['pagination']['returned']);
        $this->assertFalse($metadata['pagination']['has_more']);
        $this->assertEquals(0, $metadata['pagination']['pages']);
    }

    /**
     * Test SQL LIMIT clause generation
     */
    public function testGetLimitClause()
    {
        $clause = PaginationManager::getLimitClause(50, 100);

        $this->assertEquals('LIMIT 50 OFFSET 100', $clause);
    }

    /**
     * Test pagination is needed
     */
    public function testPaginationIsNeeded()
    {
        $this->assertTrue(PaginationManager::isNeeded(1000, 100));
        $this->assertFalse(PaginationManager::isNeeded(50, 100));
        $this->assertFalse(PaginationManager::isNeeded(100, 100));
    }

    /**
     * Test getting previous offset
     */
    public function testGetPreviousOffset()
    {
        // Not on first page
        $previous = PaginationManager::getPreviousOffset(200, 100);
        $this->assertEquals(100, $previous);

        // On first page
        $previous = PaginationManager::getPreviousOffset(0, 100);
        $this->assertNull($previous);

        // Small offset
        $previous = PaginationManager::getPreviousOffset(50, 100);
        $this->assertEquals(0, $previous);
    }

    /**
     * Test getting next offset
     */
    public function testGetNextOffset()
    {
        // Not on last page
        $next = PaginationManager::getNextOffset(0, 100, 1000);
        $this->assertEquals(100, $next);

        // On last page
        $next = PaginationManager::getNextOffset(900, 100, 1000);
        $this->assertNull($next);

        // Beyond total
        $next = PaginationManager::getNextOffset(1000, 100, 1000);
        $this->assertNull($next);
    }

    /**
     * Test building pagination links
     */
    public function testBuildLinks()
    {
        $links = PaginationManager::buildLinks(100, 50, 500, '/api/v1/users');

        $this->assertArrayHasKey('first', $links);
        $this->assertArrayHasKey('last', $links);
        $this->assertArrayHasKey('previous', $links);
        $this->assertArrayHasKey('next', $links);

        $this->assertStringContainsString('offset=0', $links['first']);
        $this->assertStringContainsString('offset=450', $links['last']);
        $this->assertStringContainsString('offset=50', $links['previous']);
        $this->assertStringContainsString('offset=150', $links['next']);
    }

    /**
     * Test links on first page
     */
    public function testBuildLinksFirstPage()
    {
        $links = PaginationManager::buildLinks(0, 100, 1000, '/api/v1/users');

        $this->assertArrayHasKey('first', $links);
        $this->assertArrayHasKey('next', $links);
        $this->assertArrayNotHasKey('previous', $links);
    }

    /**
     * Test links on last page
     */
    public function testBuildLinksLastPage()
    {
        $links = PaginationManager::buildLinks(900, 100, 1000, '/api/v1/users');

        $this->assertArrayHasKey('first', $links);
        $this->assertArrayHasKey('previous', $links);
        $this->assertArrayNotHasKey('next', $links);
    }

    /**
     * Test link format includes parameters
     */
    public function testBuildLinksFormat()
    {
        $links = PaginationManager::buildLinks(50, 25, 200, '/api/v1/items');

        $this->assertStringContainsString('limit=25', $links['first']);
        $this->assertStringContainsString('limit=25', $links['next']);
    }

    /**
     * Test empty result pagination
     */
    public function testEmptyResultPagination()
    {
        $result = PaginationManager::parse(100, 0);
        $metadata = PaginationManager::buildMetadata(0, $result['limit'], $result['offset']);

        $this->assertEquals(0, $metadata['pagination']['total']);
        $this->assertEquals(0, $metadata['pagination']['returned']);
        $this->assertEquals(0, $metadata['pagination']['pages']);
        $this->assertFalse($metadata['pagination']['has_more']);
    }

    /**
     * Test large offset with small total
     */
    public function testLargeOffsetSmallTotal()
    {
        $result = PaginationManager::parse(100, 5000);
        $metadata = PaginationManager::buildMetadata(50, $result['limit'], $result['offset']);

        $this->assertEquals(50, $metadata['pagination']['total']);
        $this->assertEquals(0, $metadata['pagination']['returned']);
        $this->assertFalse($metadata['pagination']['has_more']);
    }

    /**
     * Test page calculation accuracy
     */
    public function testPageCalculationAccuracy()
    {
        // Page 1
        $meta = PaginationManager::buildMetadata(1000, 100, 0);
        $this->assertEquals(1, $meta['pagination']['page']);

        // Page 5
        $meta = PaginationManager::buildMetadata(1000, 100, 400);
        $this->assertEquals(5, $meta['pagination']['page']);

        // Page 10
        $meta = PaginationManager::buildMetadata(1000, 100, 900);
        $this->assertEquals(10, $meta['pagination']['page']);
    }

    /**
     * Test limit at boundary values
     */
    public function testLimitBoundaryValues()
    {
        // Exactly at max
        $result = PaginationManager::parse(PaginationManager::MAX_LIMIT, 0);
        $this->assertEquals(PaginationManager::MAX_LIMIT, $result['limit']);

        // One below max
        $result = PaginationManager::parse(PaginationManager::MAX_LIMIT - 1, 0);
        $this->assertEquals(PaginationManager::MAX_LIMIT - 1, $result['limit']);

        // One above max
        $result = PaginationManager::parse(PaginationManager::MAX_LIMIT + 1, 0);
        $this->assertEquals(PaginationManager::MAX_LIMIT, $result['limit']);
    }

    /**
     * Test returned records calculation
     */
    public function testReturnedRecordsCalculation()
    {
        // Full page
        $meta = PaginationManager::buildMetadata(1000, 100, 0);
        $this->assertEquals(100, $meta['pagination']['returned']);

        // Partial page
        $meta = PaginationManager::buildMetadata(250, 100, 200);
        $this->assertEquals(50, $meta['pagination']['returned']);

        // Empty page
        $meta = PaginationManager::buildMetadata(100, 100, 200);
        $this->assertEquals(0, $meta['pagination']['returned']);
    }
}
