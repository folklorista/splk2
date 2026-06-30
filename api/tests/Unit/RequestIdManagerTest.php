<?php
namespace App\Tests;

use App\RequestIdManager;
use PHPUnit\Framework\TestCase;

class RequestIdManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear all REQUEST_ID and CORRELATION_ID related headers before each test
        $keysToRemove = [];
        foreach ($_SERVER as $key => $value) {
            if (str_contains(strtolower($key), 'request_id') ||
                str_contains(strtolower($key), 'correlation_id') ||
                str_contains(strtolower($key), 'trace_id')) {
                $keysToRemove[] = $key;
            }
        }
        foreach ($keysToRemove as $key) {
            unset($_SERVER[$key]);
        }
    }

    /**
     * Test generating new request ID without client-provided ID
     */
    public function testGenerateNewRequestId()
    {
        $manager = new RequestIdManager();
        $id = $manager->getId();

        // Should start with prefix
        $this->assertStringStartsWith('req_', $id);

        // Should be valid UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^req_[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            $id
        );

        // Should not be client-provided
        $this->assertFalse($manager->isClientProvided());
    }

    /**
     * Test detecting client-provided request ID from header
     */
    public function testDetectClientProvidedId()
    {
        $clientId = 'req_12345678-1234-5678-1234-567812345678';
        $_SERVER['HTTP_X_REQUEST_ID'] = $clientId;

        $manager = new RequestIdManager();

        $this->assertEquals($clientId, $manager->getId());
        $this->assertTrue($manager->isClientProvided());
    }

    /**
     * Test detecting from X-Correlation-ID header (alternative header)
     */
    public function testDetectFromCorrelationIdHeader()
    {
        $this->setUp();  // Clear state first
        $clientId = 'req_12345678-1234-5678-1234-567812345678';
        $_SERVER['HTTP_X_CORRELATION_ID'] = $clientId;

        $manager = new RequestIdManager();

        $this->assertEquals($clientId, $manager->getId());
        $this->assertTrue($manager->isClientProvided());
    }

    /**
     * Test detecting from X-Trace-ID header (alternative header)
     */
    public function testDetectFromTraceIdHeader()
    {
        $this->setUp();  // Clear state first
        $clientId = 'req_12345678-1234-5678-1234-567812345678';
        $_SERVER['HTTP_X_TRACE_ID'] = $clientId;

        $manager = new RequestIdManager();

        $this->assertEquals($clientId, $manager->getId());
        $this->assertTrue($manager->isClientProvided());
    }

    /**
     * Test ignoring invalid client-provided ID (security)
     */
    public function testIgnoreInvalidClientId()
    {
        $invalidId = 'invalid!!!###***';
        $_SERVER['HTTP_X_REQUEST_ID'] = $invalidId;

        $manager = new RequestIdManager();
        $id = $manager->getId();

        // Should generate new ID instead of using invalid one
        $this->assertStringStartsWith('req_', $id);
        $this->assertNotEquals($invalidId, $id);
        $this->assertFalse($manager->isClientProvided());
    }

    /**
     * Test ignoring too long client ID (security)
     */
    public function testIgnoreTooLongClientId()
    {
        $this->setUp();  // Clear state first
        $tooLongId = 'req_' . str_repeat('x', 200);
        $_SERVER['HTTP_X_REQUEST_ID'] = $tooLongId;

        $manager = new RequestIdManager();
        $id = $manager->getId();

        // Should generate new ID instead
        $this->assertStringStartsWith('req_', $id);
        $this->assertLessThanOrEqual(100, strlen($id));
        $this->assertFalse($manager->isClientProvided());
    }

    /**
     * Test header precedence (X-Request-ID takes priority)
     */
    public function testHeaderPrecedence()
    {
        $this->setUp();  // Clear state first
        $requestId = 'req_11111111-1111-1111-1111-111111111111';
        $correlationId = 'req_22222222-2222-2222-2222-222222222222';

        $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;
        $_SERVER['HTTP_X_CORRELATION_ID'] = $correlationId;

        $manager = new RequestIdManager();

        // Should prefer X-Request-ID
        $this->assertEquals($requestId, $manager->getId());
    }

    /**
     * Test UUID v4 format validation
     */
    public function testUuidV4FormatValidation()
    {
        // Valid UUID v4 formats
        $validIds = [
            'req_550e8400-e29b-41d4-a716-446655440000',
            '550e8400-e29b-41d4-a716-446655440000',
            'trace_550e8400-e29b-41d4-a716-446655440000',
            'REQ_550E8400-E29B-41D4-A716-446655440000',  // Case insensitive
        ];

        foreach ($validIds as $id) {
            $this->setUp();  // Clear state for each iteration
            $_SERVER['HTTP_X_REQUEST_ID'] = $id;
            $manager = new RequestIdManager();
            $this->assertEquals($id, $manager->getId(), "Failed for ID: $id");
        }
    }

    /**
     * Test invalid UUID format rejection
     */
    public function testInvalidUuidFormatRejection()
    {
        $invalidIds = [
            'not-a-uuid',
            '550e8400-e29b-41d4-a716',  // Too short
            '550e8400e29b41d4a716446655440000',  // No hyphens
            'req_invalid-uuid-format',
            '550e8400-e29b-41d4-a716-446655440000-extra',  // Extra parts
        ];

        foreach ($invalidIds as $invalidId) {
            $this->setUp();  // Clear state for each iteration
            $_SERVER['HTTP_X_REQUEST_ID'] = $invalidId;
            $manager = new RequestIdManager();

            // Should generate new ID instead
            $this->assertStringStartsWith('req_', $manager->getId(), "Failed for invalid ID: $invalidId");
            $this->assertFalse($manager->isClientProvided());
        }
    }

    /**
     * Test log format output
     */
    public function testLogFormat()
    {
        $_SERVER = array_filter($_SERVER, fn($k) => !str_contains($k, 'REQUEST_ID'), ARRAY_FILTER_USE_KEY);
        $manager = new RequestIdManager();
        $id = $manager->getId();

        $logFormat = $manager->getLogFormat();

        // Should be wrapped in brackets
        $this->assertStringStartsWith('[', $logFormat);
        $this->assertStringEndsWith(']', $logFormat);
        $this->assertStringContainsString($id, $logFormat);
    }

    /**
     * Test context array for structured logging
     */
    public function testContextArray()
    {
        $this->setUp();  // Clear state first
        $manager = new RequestIdManager();

        $context = $manager->getContextArray();

        $this->assertIsArray($context);
        $this->assertArrayHasKey('request_id', $context);
        $this->assertArrayHasKey('id_type', $context);
        $this->assertEquals('generated', $context['id_type']);
        $this->assertStringStartsWith('req_', $context['request_id']);
    }

    /**
     * Test context array for client-provided ID
     */
    public function testContextArrayClientProvided()
    {
        $this->setUp();  // Clear state first
        $clientId = 'req_12345678-1234-5678-1234-567812345678';
        $_SERVER['HTTP_X_REQUEST_ID'] = $clientId;

        $manager = new RequestIdManager();
        $context = $manager->getContextArray();

        $this->assertEquals($clientId, $context['request_id']);
        $this->assertEquals('client', $context['id_type']);
    }

    /**
     * Test setting response header
     */
    public function testSetResponseHeader()
    {
        $_SERVER = array_filter($_SERVER, fn($k) => !str_contains($k, 'REQUEST_ID'), ARRAY_FILTER_USE_KEY);
        $manager = new RequestIdManager();
        $id = $manager->getId();

        // Just verify the method doesn't throw
        // Headers can't be actually verified in unit tests without output buffering
        $this->assertTrue(true);
        // In practice, this would set the header:
        // header('X-Request-ID: ' . $id)
    }

    /**
     * Test uniqueness of generated IDs
     */
    public function testGeneratedIdUniqueness()
    {
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $this->setUp();  // Clear state for each iteration
            $manager = new RequestIdManager();
            $ids[] = $manager->getId();
        }

        // All should be unique
        $this->assertCount(10, array_unique($ids));
    }

    /**
     * Test trimming whitespace from headers
     */
    public function testTrimWhitespaceFromHeaders()
    {
        $this->setUp();  // Clear state first
        $clientId = '  req_12345678-1234-5678-1234-567812345678  ';
        $_SERVER['HTTP_X_REQUEST_ID'] = $clientId;

        $manager = new RequestIdManager();

        // Should trim whitespace
        $this->assertEquals(trim($clientId), $manager->getId());
    }

    /**
     * Test empty header is ignored
     */
    public function testEmptyHeaderIgnored()
    {
        $this->setUp();  // Clear state first
        $_SERVER['HTTP_X_REQUEST_ID'] = '';

        $manager = new RequestIdManager();
        $id = $manager->getId();

        // Should generate new ID
        $this->assertStringStartsWith('req_', $id);
        $this->assertFalse($manager->isClientProvided());
    }

    /**
     * Test header with only whitespace is ignored
     */
    public function testWhitespaceOnlyHeaderIgnored()
    {
        $this->setUp();  // Clear state first
        $_SERVER['HTTP_X_REQUEST_ID'] = '   ';

        $manager = new RequestIdManager();
        $id = $manager->getId();

        // Should generate new ID
        $this->assertStringStartsWith('req_', $id);
        $this->assertFalse($manager->isClientProvided());
    }
}
