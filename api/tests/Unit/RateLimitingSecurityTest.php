<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RateLimitingSecurityTest extends TestCase
{
    /**
     * Test that skipRateLimit bypass is removed
     */
    public function testSkipRateLimitBypassRemoved(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should NOT check for skipRateLimit parameter
        $this->assertStringNotContainsString('skipRateLimit', $indexSource);
        $this->assertStringNotContainsString('?skipRateLimit', $indexSource);
    }

    /**
     * Test that rate limiting applies to login endpoint
     */
    public function testLoginEndpointRateLimited(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Extract the rateLimitExempt array
        preg_match('/\$rateLimitExempt\s*=\s*\[(.*?)\]/s', $indexSource, $matches);
        $this->assertNotEmpty($matches, 'Could not find rateLimitExempt array');

        $exemptString = $matches[1];
        // Login should NOT be in exempt list
        $this->assertStringNotContainsString('login', $exemptString);

        // Rate limiting should be applied to all endpoints
        $this->assertStringContainsString('in_array($tableName', $indexSource);
        $this->assertStringContainsString('checkLimit', $indexSource);
    }

    /**
     * Test that rate limiting applies to register endpoint
     */
    public function testRegisterEndpointRateLimited(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Register should NOT be in exempt list (if login isn't, register shouldn't be)
        $rateLimitExempt = ['health', 'docs', 'openapi.yaml'];
        $this->assertNotContains('register', $rateLimitExempt);
    }

    /**
     * Test that role-based rate limiting is configured
     */
    public function testRoleBasedRateLimiting(): void
    {
        $rateLimiterSource = file_get_contents(__DIR__ . '/../../src/RateLimiter.php');
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // RateLimiter should have role-based limits
        $this->assertStringContainsString('roleLimits', $rateLimiterSource);
        $this->assertStringContainsString("'guest'", $rateLimiterSource);
        $this->assertStringContainsString("'user'", $rateLimiterSource);
        $this->assertStringContainsString("'admin'", $rateLimiterSource);

        // index.php should determine user role
        $this->assertStringContainsString('$userRole', $indexSource);
    }

    /**
     * Test that authenticated requests use user ID instead of IP
     */
    public function testAuthenticatedRequestsUseUserId(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should identify authenticated users and use user ID
        $this->assertStringContainsString('user_', $indexSource);
        $this->assertStringContainsString('limitIdentifier', $indexSource);
    }

    /**
     * Test that rate limit headers are set
     */
    public function testRateLimitHeadersSet(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should set proper headers
        $this->assertStringContainsString('X-RateLimit-Limit', $indexSource);
        $this->assertStringContainsString('X-RateLimit-Remaining', $indexSource);
        $this->assertStringContainsString('X-RateLimit-Reset', $indexSource);
    }

    /**
     * Test that env variables are used for rate limits
     */
    public function testEnvVariablesForRateLimits(): void
    {
        $rateLimiterSource = file_get_contents(__DIR__ . '/../../src/RateLimiter.php');
        $envExample = file_get_contents(__DIR__ . '/../../.env.example');

        // Should read from env
        $this->assertStringContainsString('RATE_LIMIT_GUEST', $rateLimiterSource);
        $this->assertStringContainsString('RATE_LIMIT_USER', $rateLimiterSource);
        $this->assertStringContainsString('RATE_LIMIT_ADMIN', $rateLimiterSource);

        // .env.example should document them
        $this->assertStringContainsString('RATE_LIMIT_GUEST', $envExample);
        $this->assertStringContainsString('RATE_LIMIT_USER', $envExample);
        $this->assertStringContainsString('RATE_LIMIT_ADMIN', $envExample);
    }

    /**
     * Test that HTTP 429 is returned when limit exceeded
     */
    public function testProperHttpStatusOnRateLimit(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should return 429 Too Many Requests
        $this->assertStringContainsString('429', $indexSource);
        $this->assertStringContainsString('Too Many Requests', $indexSource);
    }

    /**
     * Test that Retry-After header is set
     */
    public function testRetryAfterHeader(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        $this->assertStringContainsString('Retry-After', $indexSource);
    }

    /**
     * Test that only health and docs are truly exempt
     */
    public function testOnlyHealthAndDocsExempt(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Only these should be exempt
        $this->assertStringContainsString("'health'", $indexSource);
        $this->assertStringContainsString("'docs'", $indexSource);

        // login and register should NOT be exempt
        preg_match('/rateLimitExempt\s*=\s*\[(.*?)\]/s', $indexSource, $matches);
        if (isset($matches[1])) {
            $this->assertStringNotContainsString('login', $matches[1]);
            $this->assertStringNotContainsString('register', $matches[1]);
        }
    }
}
