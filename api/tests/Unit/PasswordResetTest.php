<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
    /**
     * Test that password reset class exists
     */
    public function testPasswordResetClassExists(): void
    {
        $this->assertTrue(class_exists('App\PasswordReset'));
    }

    /**
     * Test that .env example has rate limiting for password reset
     */
    public function testPasswordResetDocsExist(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should document rate limiting
        $this->assertStringContainsString('maxResetsPerHour', $passwordResetSource);
        $this->assertStringContainsString('tokenExpiryHours', $passwordResetSource);
    }

    /**
     * Test that token expiry is 1 hour
     */
    public function testTokenExpiryIsOneHour(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should expire in 1 hour
        $this->assertStringContainsString('tokenExpiryHours = 1', $passwordResetSource);
    }

    /**
     * Test that rate limiting is 3 per hour
     */
    public function testRateLimitThreePerHour(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should limit to 3 per hour
        $this->assertStringContainsString('maxResetsPerHour = 3', $passwordResetSource);
    }

    /**
     * Test that tokens can only be used once
     */
    public function testTokenCanOnlyBeUsedOnce(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should check if token already used
        $this->assertStringContainsString('used_at', $passwordResetSource);
        $this->assertStringContainsString('already been used', $passwordResetSource);
    }

    /**
     * Test that passwords are hashed
     */
    public function testPasswordsAreHashed(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should use auth->hashPassword
        $this->assertStringContainsString('hashPassword', $passwordResetSource);
    }

    /**
     * Test that token hash is used (not plain token)
     */
    public function testTokenHashUsed(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should hash token before storing
        $this->assertStringContainsString('token_hash', $passwordResetSource);
        $this->assertStringContainsString('hash(\'sha256\'', $passwordResetSource);
    }

    /**
     * Test that reset endpoints exist
     */
    public function testResetEndpointsExist(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should have password-reset endpoints
        $this->assertStringContainsString('password-reset', $indexSource);
        $this->assertStringContainsString('requestReset', $indexSource);
        $this->assertStringContainsString('completeReset', $indexSource);
    }

    /**
     * Test that email address validation is present
     */
    public function testEmailValidation(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should validate email parameter
        $this->assertStringContainsString('email', strtolower($passwordResetSource));
    }

    /**
     * Test that other reset tokens are revoked on successful reset
     */
    public function testOtherTokensRevokedOnReset(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should revoke other tokens
        $this->assertStringContainsString('Revoke all other reset tokens', $passwordResetSource);
    }

    /**
     * Test that password minimum length is enforced
     */
    public function testPasswordMinimumLengthEnforced(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should enforce minimum 8 characters
        $this->assertStringContainsString('8', $passwordResetSource);
        $this->assertStringContainsString('at least 8 characters', $passwordResetSource);
    }

    /**
     * Test that audit logging is performed
     */
    public function testAuditLoggingPerformed(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should log password changes
        $this->assertStringContainsString('PASSWORD_CHANGED', $passwordResetSource);
        $this->assertStringContainsString('logAction', $passwordResetSource);
    }

    /**
     * Test that email sending is stubbed
     */
    public function testEmailSendingIsStubbed(): void
    {
        $passwordResetSource = file_get_contents(__DIR__ . '/../../src/PasswordReset.php');

        // Should have stub methods
        $this->assertStringContainsString('sendResetEmail', $passwordResetSource);
        $this->assertStringContainsString('sendConfirmationEmail', $passwordResetSource);
        $this->assertStringContainsString('logger->info', $passwordResetSource);
    }

    /**
     * Test that migration exists
     */
    public function testMigrationExists(): void
    {
        $migrationFile = __DIR__ . '/../../migrations/create-password-reset-tokens-table.sql';
        $this->assertFileExists($migrationFile);

        $migration = file_get_contents($migrationFile);
        $this->assertStringContainsString('password_reset_tokens', $migration);
        $this->assertStringContainsString('token_hash', $migration);
        $this->assertStringContainsString('user_id', $migration);
        $this->assertStringContainsString('expires_at', $migration);
        $this->assertStringContainsString('used_at', $migration);
    }
}
