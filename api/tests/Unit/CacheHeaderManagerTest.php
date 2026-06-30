<?php
namespace App\Tests;

use App\CacheHeaderManager;
use PHPUnit\Framework\TestCase;

class CacheHeaderManagerTest extends TestCase
{
    /**
     * Test generating ETag from string data
     */
    public function testGenerateETagFromString()
    {
        $data = '{"id": 1, "name": "Test"}';
        $eTag = CacheHeaderManager::generateETag($data);

        // Should be valid SHA-256 hash (64 chars)
        $this->assertIsString($eTag);
        $this->assertEquals(64, strlen($eTag));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $eTag);
    }

    /**
     * Test generating ETag from array data
     */
    public function testGenerateETagFromArray()
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $eTag = CacheHeaderManager::generateETag($data);

        // Should be valid SHA-256 hash
        $this->assertIsString($eTag);
        $this->assertEquals(64, strlen($eTag));
    }

    /**
     * Test ETag consistency - same data generates same ETag
     */
    public function testETagConsistency()
    {
        $data = 'test data';

        $eTag1 = CacheHeaderManager::generateETag($data);
        $eTag2 = CacheHeaderManager::generateETag($data);

        // Same data should generate same ETag
        $this->assertEquals($eTag1, $eTag2);
    }

    /**
     * Test ETag uniqueness - different data generates different ETag
     */
    public function testETagUniqueness()
    {
        $eTag1 = CacheHeaderManager::generateETag('data1');
        $eTag2 = CacheHeaderManager::generateETag('data2');

        // Different data should generate different ETags
        $this->assertNotEquals($eTag1, $eTag2);
    }

    /**
     * Test getting cache strategy for health endpoint
     */
    public function testGetCacheStrategyForHealth()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('health', 'GET');

        $this->assertIsArray($strategy);
        $this->assertArrayHasKey('max_age', $strategy);
        $this->assertArrayHasKey('public', $strategy);
        $this->assertEquals(300, $strategy['max_age']);  // 5 minutes
        $this->assertTrue($strategy['public']);
    }

    /**
     * Test getting cache strategy for versions endpoint
     */
    public function testGetCacheStrategyForVersions()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('versions', 'GET');

        $this->assertEquals(3600, $strategy['max_age']);  // 1 hour
        $this->assertTrue($strategy['public']);
    }

    /**
     * Test getting cache strategy for docs endpoint
     */
    public function testGetCacheStrategyForDocs()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('docs', 'GET');

        $this->assertEquals(86400, $strategy['max_age']);  // 1 day
        $this->assertTrue($strategy['public']);
    }

    /**
     * Test getting default cache strategy for unknown endpoint
     */
    public function testGetDefaultCacheStrategy()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('unknown-endpoint', 'GET');

        $this->assertEquals(0, $strategy['max_age']);     // No cache
        $this->assertFalse($strategy['public']);         // Private
    }

    /**
     * Test that POST requests get no-cache strategy
     */
    public function testPostRequestsNotCached()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('health', 'POST');

        $this->assertEquals(0, $strategy['max_age']);
        $this->assertFalse($strategy['public']);
    }

    /**
     * Test that PUT requests get no-cache strategy
     */
    public function testPutRequestsNotCached()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('health', 'PUT');

        $this->assertEquals(0, $strategy['max_age']);
        $this->assertFalse($strategy['public']);
    }

    /**
     * Test that DELETE requests get no-cache strategy
     */
    public function testDeleteRequestsNotCached()
    {
        $strategy = CacheHeaderManager::getCacheStrategy('health', 'DELETE');

        $this->assertEquals(0, $strategy['max_age']);
        $this->assertFalse($strategy['public']);
    }

    /**
     * Test shouldCache returns true for cacheable GET request
     */
    public function testShouldCacheForGET()
    {
        $should = CacheHeaderManager::shouldCache('health', 'GET', 200);

        $this->assertTrue($should);
    }

    /**
     * Test shouldCache returns true for cacheable HEAD request
     */
    public function testShouldCacheForHEAD()
    {
        $should = CacheHeaderManager::shouldCache('health', 'HEAD', 200);

        $this->assertTrue($should);
    }

    /**
     * Test shouldCache returns false for POST request
     */
    public function testShouldNotCacheForPOST()
    {
        $should = CacheHeaderManager::shouldCache('health', 'POST', 200);

        $this->assertFalse($should);
    }

    /**
     * Test shouldCache returns false for error responses
     */
    public function testShouldNotCacheErrorResponses()
    {
        $this->assertFalse(CacheHeaderManager::shouldCache('health', 'GET', 400));
        $this->assertFalse(CacheHeaderManager::shouldCache('health', 'GET', 404));
        $this->assertFalse(CacheHeaderManager::shouldCache('health', 'GET', 500));
    }

    /**
     * Test shouldCache returns false for endpoints with no-cache strategy
     */
    public function testShouldNotCacheNocachEndpoints()
    {
        $should = CacheHeaderManager::shouldCache('users', 'GET', 200);

        $this->assertFalse($should);  // users endpoint has max_age=0
    }

    /**
     * Test shouldCache accepts 304 Not Modified responses
     */
    public function testShouldCacheNotModifiedResponse()
    {
        $should = CacheHeaderManager::shouldCache('health', 'GET', 304);

        $this->assertTrue($should);
    }

    /**
     * Test isClientCacheValid with matching ETag
     */
    public function testIsClientCacheValidWithMatchingETag()
    {
        $eTag = 'abc123def456';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $eTag;

        $isValid = CacheHeaderManager::isClientCacheValid($eTag);

        $this->assertTrue($isValid);
    }

    /**
     * Test isClientCacheValid with quoted ETag
     */
    public function testIsClientCacheValidWithQuotedETag()
    {
        $eTag = 'abc123def456';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . $eTag . '"';

        $isValid = CacheHeaderManager::isClientCacheValid($eTag);

        $this->assertTrue($isValid);
    }

    /**
     * Test isClientCacheValid with mismatched ETag
     */
    public function testIsClientCacheInvalidWithMismatchedETag()
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = 'old-etag-value';

        $isValid = CacheHeaderManager::isClientCacheValid('new-etag-value');

        $this->assertFalse($isValid);
    }

    /**
     * Test isClientCacheValid with If-Modified-Since header
     */
    public function testIsClientCacheValidWithIfModifiedSince()
    {
        $_SERVER = array_filter($_SERVER, fn($k) => $k !== 'HTTP_IF_NONE_MATCH', ARRAY_FILTER_USE_KEY);
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Mon, 29 Jun 2026 12:00:00 GMT';

        $isValid = CacheHeaderManager::isClientCacheValid('some-etag');

        $this->assertTrue($isValid);
    }

    /**
     * Test isClientCacheValid without any validation headers
     */
    public function testIsClientCacheInvalidWithoutHeaders()
    {
        $_SERVER = array_filter($_SERVER, fn($k) => !str_contains($k, 'IF_'), ARRAY_FILTER_USE_KEY);

        $isValid = CacheHeaderManager::isClientCacheValid('some-etag');

        $this->assertFalse($isValid);
    }

    /**
     * Test getAllStrategies returns all defined strategies
     */
    public function testGetAllStrategies()
    {
        $strategies = CacheHeaderManager::getAllStrategies();

        $this->assertIsArray($strategies);
        $this->assertArrayHasKey('health', $strategies);
        $this->assertArrayHasKey('versions', $strategies);
        $this->assertArrayHasKey('docs', $strategies);
        $this->assertArrayHasKey('default', $strategies);
    }

    /**
     * Test cache strategy structure
     */
    public function testCacheStrategyStructure()
    {
        $strategies = CacheHeaderManager::getAllStrategies();

        foreach ($strategies as $name => $strategy) {
            $this->assertArrayHasKey('max_age', $strategy, "Missing max_age in $name");
            $this->assertArrayHasKey('public', $strategy, "Missing public in $name");
            $this->assertIsInt($strategy['max_age'], "max_age should be int in $name");
            $this->assertIsBool($strategy['public'], "public should be bool in $name");
        }
    }

    /**
     * Test cache max_age values are reasonable
     */
    public function testCacheMaxAgeValues()
    {
        $strategies = CacheHeaderManager::getAllStrategies();

        // All max_age values should be >= 0
        foreach ($strategies as $strategy) {
            $this->assertGreaterThanOrEqual(0, $strategy['max_age']);
            // All should be reasonable (not more than 30 days)
            $this->assertLessThanOrEqual(30 * 86400, $strategy['max_age']);
        }
    }

    /**
     * Test public/private classification
     */
    public function testPublicPrivateClassification()
    {
        $strategies = CacheHeaderManager::getAllStrategies();

        // health, versions, docs should be public
        $this->assertTrue($strategies['health']['public']);
        $this->assertTrue($strategies['versions']['public']);
        $this->assertTrue($strategies['docs']['public']);

        // default should be private
        $this->assertFalse($strategies['default']['public']);
    }
}
