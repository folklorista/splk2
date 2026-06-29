<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class JwtSecretConfigTest extends TestCase
{
    /**
     * Test that missing JWT_SECRET throws exception
     */
    public function testMissingJwtSecretThrowsException()
    {
        // Save original
        $originalSecret = $_ENV['JWT_SECRET'] ?? null;

        try {
            // Unset JWT_SECRET
            unset($_ENV['JWT_SECRET']);

            // Try to load config - should throw
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('JWT_SECRET environment variable is not set');

            // This will throw when config validates
            require __DIR__ . '/../../config/config.local.php';
        } finally {
            // Restore original
            if ($originalSecret !== null) {
                $_ENV['JWT_SECRET'] = $originalSecret;
            }
        }
    }

    /**
     * Test that empty JWT_SECRET throws exception
     */
    public function testEmptyJwtSecretThrowsException()
    {
        $originalSecret = $_ENV['JWT_SECRET'] ?? null;

        try {
            // Set empty JWT_SECRET
            $_ENV['JWT_SECRET'] = '';

            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('JWT_SECRET environment variable is not set');

            require __DIR__ . '/../../config/config.local.php';
        } finally {
            if ($originalSecret !== null) {
                $_ENV['JWT_SECRET'] = $originalSecret;
            }
        }
    }

    /**
     * Test that valid JWT_SECRET is accepted
     */
    public function testValidJwtSecretLoads()
    {
        $originalSecret = $_ENV['JWT_SECRET'] ?? null;

        try {
            // Set a valid JWT_SECRET
            $_ENV['JWT_SECRET'] = 'test-secret-at-least-32-characters-long-for-security';

            // Config should load without error
            $config = require __DIR__ . '/../../config/config.local.php';

            $this->assertIsArray($config);
            $this->assertArrayHasKey('auth', $config);
            $this->assertEquals($_ENV['JWT_SECRET'], $config['auth']['jwt_secret']);
        } finally {
            if ($originalSecret !== null) {
                $_ENV['JWT_SECRET'] = $originalSecret;
            } else {
                unset($_ENV['JWT_SECRET']);
            }
        }
    }

    /**
     * Test that JWT_SECRET is not hardcoded
     */
    public function testJwtSecretNotHardcoded()
    {
        // Read config file
        $configFile = file_get_contents(__DIR__ . '/../../config/config.local.php');

        // Should NOT contain any hardcoded secrets
        $this->assertStringNotContainsString("'my_little_secret'", $configFile);
        $this->assertStringNotContainsString("'my-secret'", $configFile);

        // Should reference env variable (in the auth section)
        $this->assertStringContainsString('$_ENV[\'JWT_SECRET\']', $configFile);

        // Should have validation that enforces it
        $this->assertStringContainsString('empty($_ENV[\'JWT_SECRET\'])', $configFile);
    }

    /**
     * Test that error message is helpful
     */
    public function testErrorMessageIsHelpful()
    {
        $originalSecret = $_ENV['JWT_SECRET'] ?? null;

        try {
            unset($_ENV['JWT_SECRET']);

            // Capture exception
            $exceptionThrown = false;
            $exceptionMessage = '';

            try {
                require __DIR__ . '/../../config/config.local.php';
            } catch (\Exception $e) {
                $exceptionThrown = true;
                $exceptionMessage = $e->getMessage();
            }

            $this->assertTrue($exceptionThrown, 'Should throw exception when JWT_SECRET missing');

            // Verify error message includes guidance
            $this->assertStringContainsString('JWT_SECRET', $exceptionMessage);
            $this->assertStringContainsString('not set', $exceptionMessage);
            $this->assertStringContainsString('.env', $exceptionMessage);
        } finally {
            if ($originalSecret !== null) {
                $_ENV['JWT_SECRET'] = $originalSecret;
            }
        }
    }

    /**
     * Test that .env.example doesn't contain hardcoded secret
     */
    public function testEnvExampleNoHardcodedSecret()
    {
        $envExample = file_get_contents(__DIR__ . '/../../.env.example');

        // Should NOT contain any hardcoded defaults that look like secrets
        $this->assertStringNotContainsString("JWT_SECRET=my_little_secret", $envExample);
        $this->assertStringNotContainsString("JWT_SECRET=secret", $envExample);

        // Should have placeholder/example
        $this->assertStringContainsString('JWT_SECRET=', $envExample);

        // Should have guidance
        $this->assertStringContainsString('REQUIRED', $envExample);
    }
}
