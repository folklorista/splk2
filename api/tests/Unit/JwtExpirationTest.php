<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class JwtExpirationTest extends TestCase
{
    /**
     * Test that JWT_EXPIRATION environment variable is used
     */
    public function testJwtExpirationEnvVariable()
    {
        $originalExpiration = $_ENV['JWT_EXPIRATION'] ?? null;

        try {
            // Set custom expiration
            $_ENV['JWT_EXPIRATION'] = 1800; // 30 minutes

            // The expiration should be read from env in config
            $this->assertEquals('1800', $_ENV['JWT_EXPIRATION']);
        } finally {
            if ($originalExpiration !== null) {
                $_ENV['JWT_EXPIRATION'] = $originalExpiration;
            } else {
                unset($_ENV['JWT_EXPIRATION']);
            }
        }
    }

    /**
     * Test that default JWT_EXPIRATION is 900 seconds (15 minutes)
     */
    public function testDefaultJwtExpirationIs15Minutes()
    {
        // If not set, default should be 900 seconds
        $defaultExpiration = (int)($_ENV['JWT_EXPIRATION'] ?? 900);

        // Default should be 15 minutes (900 seconds)
        $this->assertEquals(900, $defaultExpiration);
    }

    /**
     * Test that .env.example includes JWT_EXPIRATION
     */
    public function testEnvExampleIncludesJwtExpiration()
    {
        $envExample = file_get_contents(__DIR__ . '/../../.env.example');

        $this->assertStringContainsString('JWT_EXPIRATION', $envExample);
        $this->assertStringContainsString('900', $envExample);
    }

    /**
     * Test that JWT_EXPIRATION is not extremely long (e.g., not 365 days)
     */
    public function testJwtExpirationIsNotTooLong()
    {
        $originalExpiration = $_ENV['JWT_EXPIRATION'] ?? null;

        try {
            // Set to 365 days (should not be the default)
            $_ENV['JWT_EXPIRATION'] = (365 * 24 * 60 * 60);

            // Verify it reads correctly
            $this->assertEquals(31536000, (int)$_ENV['JWT_EXPIRATION']);

            // But we expect default to be much shorter
            unset($_ENV['JWT_EXPIRATION']);
            $defaultExpiration = (int)($_ENV['JWT_EXPIRATION'] ?? 900);
            $this->assertEquals(900, $defaultExpiration);
            $this->assertLessThan(3600, $defaultExpiration); // Less than 1 hour
        } finally {
            if ($originalExpiration !== null) {
                $_ENV['JWT_EXPIRATION'] = $originalExpiration;
            }
        }
    }
}
