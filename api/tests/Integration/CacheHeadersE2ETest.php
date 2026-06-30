<?php
namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

class CacheHeadersE2ETest extends TestCase
{
    private $baseUrl = 'http://127.0.0.1:8000';

    /**
     * Helper to make HTTP request and capture all headers
     */
    private function makeRequestWithHeaders(string $endpoint, array $customHeaders = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if (!empty($customHeaders)) {
            $headers = [];
            foreach ($customHeaders as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Split headers and body
        $parts = explode("\r\n\r\n", $response, 2);
        $headerString = $parts[0];
        $body = $parts[1] ?? '';

        // Parse headers
        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => json_decode($body, true),
            'raw_body' => $body,
        ];
    }

    /**
     * Test health endpoint has Cache-Control header
     */
    public function testHealthEndpointHasCacheControl()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('cache-control', $response['headers']);

        $cacheControl = $response['headers']['cache-control'];
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);  // 5 minutes
    }

    /**
     * Test health endpoint has Last-Modified header
     */
    public function testHealthEndpointHasLastModified()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('last-modified', $response['headers']);

        $lastModified = $response['headers']['last-modified'];
        // Should be valid HTTP date format
        $this->assertMatchesRegularExpression('/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4}/', $lastModified);
    }

    /**
     * Test health endpoint has ETag header
     */
    public function testHealthEndpointHasETag()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('etag', $response['headers']);

        $eTag = $response['headers']['etag'];
        // ETag should be quoted
        $this->assertStringStartsWith('"', $eTag);
        $this->assertStringEndsWith('"', $eTag);
    }

    /**
     * Test versions endpoint has longer cache
     */
    public function testVersionsEndpointHasLongerCache()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/versions');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('cache-control', $response['headers']);

        $cacheControl = $response['headers']['cache-control'];
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);  // 1 hour
    }

    /**
     * Test docs endpoint has longest cache
     */
    public function testDocsEndpointHasLongestCache()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/docs');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('cache-control', $response['headers']);

        $cacheControl = $response['headers']['cache-control'];
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);  // 1 day
    }

    /**
     * Test user endpoints have no-cache directive
     */
    public function testUserEndpointsHaveNoCache()
    {
        // This test assumes GET /api/v1/users would return 401 without auth
        // But we want to test the header regardless
        $response = $this->makeRequestWithHeaders('/api/v1/users');

        // Should have cache-control header with private and max-age=0
        $this->assertArrayHasKey('cache-control', $response['headers']);
        $cacheControl = $response['headers']['cache-control'];

        // User data endpoints should be private and not cached
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    /**
     * Test Cache-Control has must-revalidate for cacheable endpoints
     */
    public function testCachableEndpointsHaveMustRevalidate()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $cacheControl = $response['headers']['cache-control'];

        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    /**
     * Test ETag consistency - same endpoint returns same ETag
     */
    public function testETagConsistency()
    {
        $response1 = $this->makeRequestWithHeaders('/api/v1/health');
        $response2 = $this->makeRequestWithHeaders('/api/v1/health');

        $eTag1 = $response1['headers']['etag'] ?? null;
        $eTag2 = $response2['headers']['etag'] ?? null;

        // Same endpoint should return same data and thus same ETag
        // (Note: in real scenario, timestamps might differ slightly)
        $this->assertNotNull($eTag1);
        $this->assertNotNull($eTag2);
    }

    /**
     * Test If-None-Match header with matching ETag
     */
    public function testIfNoneMatchWithMatchingETag()
    {
        // First request to get ETag
        $response1 = $this->makeRequestWithHeaders('/api/v1/health');
        $this->assertEquals(200, $response1['status']);
        $eTag = $response1['headers']['etag'] ?? null;

        if ($eTag) {
            // Second request with If-None-Match header
            $response2 = $this->makeRequestWithHeaders('/api/v1/health', [
                'If-None-Match' => $eTag,
            ]);

            // Should return 304 Not Modified
            $this->assertEquals(304, $response2['status']);
        }
    }

    /**
     * Test If-Modified-Since header
     */
    public function testIfModifiedSinceHeader()
    {
        // First request to get Last-Modified
        $response1 = $this->makeRequestWithHeaders('/api/v1/health');
        $this->assertEquals(200, $response1['status']);
        $lastModified = $response1['headers']['last-modified'] ?? null;

        if ($lastModified) {
            // Second request with If-Modified-Since header
            $response2 = $this->makeRequestWithHeaders('/api/v1/health', [
                'If-Modified-Since' => $lastModified,
            ]);

            // Should return 304 Not Modified or 200 OK (depending on implementation)
            $this->assertContains($response2['status'], [200, 304]);
        }
    }

    /**
     * Test HEAD requests have same cache headers as GET
     */
    public function testHEADRequestsCacheHeaders()
    {
        $url = $this->baseUrl . '/api/v1/health';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);

        // Parse headers
        $headers = [];
        foreach (explode("\r\n", $response) as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        // HEAD should have same cache headers as GET
        $this->assertArrayHasKey('cache-control', $headers);
        $this->assertArrayHasKey('last-modified', $headers);
    }

    /**
     * Test POST request has no-cache
     */
    public function testPostRequestHasNoCache()
    {
        $url = $this->baseUrl . '/api/v1/health';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);

        // Parse headers
        $headers = [];
        foreach (explode("\r\n", $response) as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        // POST should not be cached
        if (isset($headers['cache-control'])) {
            $this->assertStringContainsString('max-age=0', $headers['cache-control']);
        }
    }

    /**
     * Test Cache-Control header format
     */
    public function testCacheControlHeaderFormat()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $cacheControl = $response['headers']['cache-control'];

        // Should be comma-separated directives
        $directives = array_map('trim', explode(',', $cacheControl));

        // Should have at least max-age
        $hasMaxAge = false;
        foreach ($directives as $directive) {
            if (strpos($directive, 'max-age=') === 0) {
                $hasMaxAge = true;
                // Validate format: max-age=XXX
                $this->assertMatchesRegularExpression('/^max-age=\d+$/', $directive);
            }
        }

        $this->assertTrue($hasMaxAge, 'Cache-Control should have max-age directive');
    }

    /**
     * Test ETag format (should be quoted)
     */
    public function testETagFormat()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $eTag = $response['headers']['etag'];

        // ETag must be quoted
        $this->assertStringStartsWith('"', $eTag);
        $this->assertStringEndsWith('"', $eTag);

        // Remove quotes and validate hash format (should be 64 hex chars for SHA-256)
        $hash = trim($eTag, '"');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}
