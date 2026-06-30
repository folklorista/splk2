<?php
namespace App\Tests;

use App\ApiRouter;
use PHPUnit\Framework\TestCase;

class ApiVersioningTest extends TestCase
{
    /**
     * Test parsing v1 endpoint with versioned URL format
     */
    public function testParseV1VersionedUrl()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/api/v1/users/123');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('users', $routing['resource']);
        $this->assertEquals('123', $routing['id']);
        $this->assertEquals('GET', $routing['method']);
    }

    /**
     * Test parsing v2 endpoint throws error (not yet supported)
     */
    public function testParseV2VersionedUrlNotSupported()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported API version: v2');

        ApiRouter::parseRequest('/api/v2/items');
    }

    /**
     * Test parsing legacy format without /api prefix (should default to v1)
     */
    public function testParseLegacyFormat()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/users/456');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('users', $routing['resource']);
        $this->assertEquals('456', $routing['id']);
    }

    /**
     * Test parsing with trailing slash
     */
    public function testParseWithTrailingSlash()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/api/v1/users/');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('users', $routing['resource']);
    }

    /**
     * Test parsing endpoint without ID
     */
    public function testParseEndpointWithoutId()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/api/v1/users');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('users', $routing['resource']);
        $this->assertNull($routing['id']);
    }

    /**
     * Test parsing with query parameters
     */
    public function testParseWithQueryParams()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/api/v1/users?limit=10&offset=0');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('users', $routing['resource']);
        // Query params are not extracted by parseRequest, handled separately
    }

    /**
     * Test unsupported version throws exception
     */
    public function testUnsupportedVersionThrowsException()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported API version: v99');

        ApiRouter::parseRequest('/api/v99/users');
    }

    /**
     * Test version string validation
     */
    public function testVersionStringValidation()
    {
        $this->assertTrue(ApiRouter::isVersionSupported('v1'));
        $this->assertFalse(ApiRouter::isVersionSupported('v2'));  // Not yet supported
        $this->assertFalse(ApiRouter::isVersionSupported('v99'));
        $this->assertFalse(ApiRouter::isVersionSupported('version1'));
        $this->assertFalse(ApiRouter::isVersionSupported('1'));
    }

    /**
     * Test get supported versions
     */
    public function testGetSupportedVersions()
    {
        $versions = ApiRouter::getSupportedVersions();

        $this->assertIsArray($versions);
        $this->assertContains('v1', $versions);
        $this->assertCount(1, $versions);  // Only v1 supported currently
    }

    /**
     * Test pathIndex structure is correct for routing
     */
    public function testPathIndexStructure()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/api/v1/users/123');

        $this->assertArrayHasKey('pathIndex', $routing);
        $this->assertEquals(0, $routing['pathIndex']['table']);
        $this->assertEquals(1, $routing['pathIndex']['id']);
    }

    /**
     * Test path array structure
     */
    public function testPathArrayStructure()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/api/v1/users/123/details');

        $this->assertIsArray($routing['path']);
        $this->assertEquals('users', $routing['path'][0]);
        $this->assertEquals('123', $routing['path'][1]);
        $this->assertEquals('details', $routing['path'][2]);
    }

    /**
     * Test health endpoint (no version needed)
     */
    public function testHealthEndpointParsing()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/health');

        // Health should work without version (defaults to v1)
        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('health', $routing['resource']);
    }

    /**
     * Test docs endpoint parsing
     */
    public function testDocsEndpointParsing()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/docs');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('docs', $routing['resource']);
    }

    /**
     * Test versions endpoint parsing
     */
    public function testVersionsEndpointParsing()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routing = ApiRouter::parseRequest('/versions');

        $this->assertEquals('v1', $routing['version']);
        $this->assertEquals('versions', $routing['resource']);
    }
}
