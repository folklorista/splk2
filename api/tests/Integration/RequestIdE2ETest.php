<?php
namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

class RequestIdE2ETest extends TestCase
{
    private $baseUrl = 'http://127.0.0.1:8000';

    /**
     * Helper to make HTTP request and capture headers
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
     * Test that API generates request ID for requests without client-provided ID
     */
    public function testApiGeneratesRequestId()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('x-request-id', $response['headers']);

        $requestId = $response['headers']['x-request-id'];
        $this->assertStringStartsWith('req_', $requestId);
        $this->assertMatchesRegularExpression('/^req_[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $requestId);
    }

    /**
     * Test that API accepts client-provided request ID
     */
    public function testApiAcceptsClientRequestId()
    {
        $clientId = 'req_12345678-1234-5678-1234-567812345678';

        $response = $this->makeRequestWithHeaders('/api/v1/health', [
            'X-Request-ID' => $clientId,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('x-request-id', $response['headers']);
        $this->assertEquals($clientId, $response['headers']['x-request-id']);
    }

    /**
     * Test that API accepts correlation ID header as alternative
     */
    public function testApiAcceptsCorrelationIdHeader()
    {
        $clientId = 'req_87654321-4321-8765-4321-876543210000';

        $response = $this->makeRequestWithHeaders('/api/v1/health', [
            'X-Correlation-ID' => $clientId,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('x-request-id', $response['headers']);
        $this->assertEquals($clientId, $response['headers']['x-request-id']);
    }

    /**
     * Test that API generates new ID for invalid client-provided ID
     */
    public function testApiGeneratesNewIdForInvalidClientId()
    {
        $invalidId = 'invalid!!!';

        $response = $this->makeRequestWithHeaders('/api/v1/health', [
            'X-Request-ID' => $invalidId,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('x-request-id', $response['headers']);

        $returnedId = $response['headers']['x-request-id'];
        $this->assertStringStartsWith('req_', $returnedId);
        $this->assertNotEquals($invalidId, $returnedId);
    }

    /**
     * Test that request ID is present in different endpoints
     */
    public function testRequestIdInDifferentEndpoints()
    {
        $endpoints = [
            '/api/v1/health',
            '/api/v1/versions',
            '/api/v1/docs',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->makeRequestWithHeaders($endpoint);
            $this->assertArrayHasKey('x-request-id', $response['headers'], "Missing X-Request-ID in $endpoint");
            $requestId = $response['headers']['x-request-id'];
            $this->assertStringStartsWith('req_', $requestId, "Invalid request ID format in $endpoint");
        }
    }

    /**
     * Test that each request gets unique ID (when not provided by client)
     */
    public function testEachRequestGetsUniqueId()
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->makeRequestWithHeaders('/api/v1/health');
            $ids[] = $response['headers']['x-request-id'];
        }

        // All IDs should be unique
        $uniqueIds = array_unique($ids);
        $this->assertCount(5, $uniqueIds, 'Not all request IDs are unique');
    }

    /**
     * Test that client ID is preserved across request
     */
    public function testClientIdPreservedInResponse()
    {
        // Must use valid UUID format for client ID
        $clientId = 'req_12345678-1234-5678-1234-567812345678';

        $response = $this->makeRequestWithHeaders('/api/v1/health', [
            'X-Request-ID' => $clientId,
        ]);

        // Should return the exact same ID
        $this->assertEquals($clientId, $response['headers']['x-request-id']);
    }

    /**
     * Test that request ID works with legacy URL format
     */
    public function testRequestIdWithLegacyUrl()
    {
        $response = $this->makeRequestWithHeaders('/health');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('x-request-id', $response['headers']);
        $requestId = $response['headers']['x-request-id'];
        $this->assertStringStartsWith('req_', $requestId);
    }

    /**
     * Test that request ID appears in error responses
     */
    public function testRequestIdInErrorResponse()
    {
        $response = $this->makeRequestWithHeaders('/api/v1/invalid-endpoint');

        // Should return error but still have request ID
        $this->assertArrayHasKey('x-request-id', $response['headers']);
        $requestId = $response['headers']['x-request-id'];
        $this->assertStringStartsWith('req_', $requestId);
    }

    /**
     * Test request ID format with whitespace in client header
     */
    public function testRequestIdWithWhitespaceInClientHeader()
    {
        $clientId = '  req_12345678-1234-5678-1234-567812345678  ';

        $response = $this->makeRequestWithHeaders('/api/v1/health', [
            'X-Request-ID' => $clientId,
        ]);

        // Should trim and accept
        $this->assertEquals(trim($clientId), $response['headers']['x-request-id']);
    }

    /**
     * Test that invalid long client ID is rejected
     */
    public function testInvalidLongClientIdIsRejected()
    {
        $tooLongId = 'req_' . str_repeat('x', 200);

        $response = $this->makeRequestWithHeaders('/api/v1/health', [
            'X-Request-ID' => $tooLongId,
        ]);

        // Should generate new ID instead
        $returnedId = $response['headers']['x-request-id'];
        $this->assertStringStartsWith('req_', $returnedId);
        $this->assertLessThanOrEqual(100, strlen($returnedId));
        $this->assertNotEquals($tooLongId, $returnedId);
    }
}
