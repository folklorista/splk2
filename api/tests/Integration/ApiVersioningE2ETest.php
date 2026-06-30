<?php
namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

class ApiVersioningE2ETest extends TestCase
{
    private $baseUrl = 'http://127.0.0.1:8000';

    /**
     * Helper to make HTTP request
     */
    private function makeRequest(string $endpoint, string $method = 'GET', $data = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'body' => json_decode($response, true),
            'raw' => $response,
        ];
    }

    /**
     * Test v1 endpoint with versioned URL
     */
    public function testV1VersionedEndpoint()
    {
        $response = $this->makeRequest('/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('status', $response['body']);
    }

    /**
     * Test v2 endpoint not yet supported
     */
    public function testV2VersionedEndpointNotSupported()
    {
        $response = $this->makeRequest('/api/v2/health');

        // v2 is not yet supported
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('Unsupported', $response['raw']);
    }

    /**
     * Test legacy endpoint without /api prefix (backwards compatibility)
     */
    public function testLegacyEndpoint()
    {
        $response = $this->makeRequest('/health');

        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('status', $response['body']);
    }

    /**
     * Test versions endpoint returns version information
     */
    public function testVersionsEndpoint()
    {
        $response = $this->makeRequest('/versions');

        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertArrayHasKey('supported_versions', $response['body']['data']);

        $versions = $response['body']['data']['supported_versions'];
        $this->assertIsArray($versions);
        $this->assertNotEmpty($versions);

        // Check that v1 and v2 are listed
        $versionStrings = array_column($versions, 'version');
        $this->assertContains('v1', $versionStrings);
        $this->assertContains('v2', $versionStrings);
    }

    /**
     * Test API response includes version header
     */
    public function testResponseIncludesVersionHeader()
    {
        $url = $this->baseUrl . '/api/v1/health';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);

        // Check that X-API-Version header is present
        $this->assertStringContainsString('X-API-Version: v1', $response);
    }

    /**
     * Test unsupported version returns error
     */
    public function testUnsupportedVersionReturnsError()
    {
        $response = $this->makeRequest('/api/v99/health');

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('status', $response['body']);
        $this->assertStringContainsString('Unsupported', $response['raw']);
    }

    /**
     * Test docs endpoint works with versioned URL
     */
    public function testDocsWithVersionedUrl()
    {
        $url = $this->baseUrl . '/api/v1/docs';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Docs should work with versioned URL
        $this->assertEquals(200, $statusCode);
    }

    /**
     * Test docs endpoint works with legacy URL
     */
    public function testDocsWithLegacyUrl()
    {
        $url = $this->baseUrl . '/docs';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Legacy docs should also work
        $this->assertEquals(200, $statusCode);
    }
}
