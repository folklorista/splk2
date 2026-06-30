<?php
namespace App;

use Exception;

class PasswordReset
{
    private $db;
    private $logger;
    private $auth;
    private $tokenExpiryHours = 1;
    private $maxResetsPerHour = 3;

    public function __construct(Database $db, Logger $logger, Auth $auth)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->auth = $auth;
    }

    /**
     * Initiate password reset for user
     * Generates token and stores it (email sending is stubbed)
     */
    public function requestReset(string $email): array
    {
        try {
            $userResult = $this->db->get('users', $email, 'email');
            if (!$userResult || $userResult['status'] !== 200) {
                // Don't reveal if user exists
                $this->logger->warning('Password reset requested for non-existent email', ['email' => $email]);
                return [
                    'status' => 200,
                    'message' => 'If email exists, reset link has been sent',
                    'data' => null,
                ];
            }

            $user = $userResult['data'];

            // Rate limit: max 3 resets per hour per email
            $recentResets = $this->getRecentResetCount($user['id'], 3600); // 1 hour
            if ($recentResets >= $this->maxResetsPerHour) {
                $this->logger->warning('Password reset rate limit exceeded', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'recent_resets' => $recentResets,
                ]);
                return [
                    'status' => 429,
                    'message' => 'Too many reset requests. Please try again later.',
                    'data' => null,
                ];
            }

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + ($this->tokenExpiryHours * 3600));

            // Store token hash in database
            $this->db->execute(
                'INSERT INTO password_reset_tokens (token_hash, user_id, expires_at) VALUES (:token_hash, :user_id, :expires_at)',
                [
                    ':token_hash' => $tokenHash,
                    ':user_id' => $user['id'],
                    ':expires_at' => $expiresAt,
                ]
            );

            $this->logger->info('Password reset requested', [
                'user_id' => $user['id'],
                'email' => $email,
            ]);

            // Send email (stubbed - in production would use actual email service)
            $this->sendResetEmail($email, $user['first_name'], $token);

            return [
                'status' => 200,
                'message' => 'If email exists, reset link has been sent',
                'data' => null,
            ];
        } catch (Exception $e) {
            $this->logger->error('Error requesting password reset', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 500,
                'message' => 'Error processing request',
                'data' => null,
            ];
        }
    }

    /**
     * Complete password reset with token
     */
    public function completeReset(string $token, string $newPassword): array
    {
        try {
            if (empty($token) || empty($newPassword)) {
                return [
                    'status' => 400,
                    'message' => 'Token and password are required',
                    'data' => null,
                ];
            }

            if (strlen($newPassword) < 8) {
                return [
                    'status' => 400,
                    'message' => 'Password must be at least 8 characters',
                    'data' => null,
                ];
            }

            $tokenHash = hash('sha256', $token);

            // Find token
            $tokenRecord = $this->db->fetchOne(
                'SELECT id, user_id, expires_at, used_at FROM password_reset_tokens WHERE token_hash = :token_hash',
                [':token_hash' => $tokenHash]
            );

            if (!$tokenRecord) {
                $this->logger->warning('Invalid password reset token used');
                return [
                    'status' => 400,
                    'message' => 'Invalid or expired reset token',
                    'data' => null,
                ];
            }

            // Check if token already used
            if ($tokenRecord['used_at'] !== null) {
                $this->logger->warning('Password reset token already used', [
                    'user_id' => $tokenRecord['user_id'],
                ]);
                return [
                    'status' => 400,
                    'message' => 'This reset link has already been used',
                    'data' => null,
                ];
            }

            // Check if token expired
            $expiresAt = strtotime($tokenRecord['expires_at']);
            if ($expiresAt < time()) {
                $this->logger->warning('Password reset token expired', [
                    'user_id' => $tokenRecord['user_id'],
                ]);
                return [
                    'status' => 400,
                    'message' => 'Reset token has expired',
                    'data' => null,
                ];
            }

            // Get user
            $userResult = $this->db->get('users', $tokenRecord['user_id']);
            if (!$userResult || $userResult['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            $user = $userResult['data'];

            // Update password
            $hashedPassword = $this->auth->hashPassword($newPassword);
            $this->db->execute(
                'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id',
                [
                    ':password' => $hashedPassword,
                    ':id' => $user['id'],
                ]
            );

            // Mark token as used
            $this->db->execute(
                'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id',
                [':id' => $tokenRecord['id']]
            );

            // Revoke all other reset tokens for this user
            $this->db->execute(
                'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND id != :token_id AND used_at IS NULL',
                [
                    ':user_id' => $user['id'],
                    ':token_id' => $tokenRecord['id'],
                ]
            );

            // Log the password reset
            $this->db->logAction(AuditAction::PASSWORD_CHANGED, $user['id']);

            $this->logger->info('Password reset completed', [
                'user_id' => $user['id'],
            ]);

            // Send confirmation email
            $this->sendConfirmationEmail($user['email'], $user['first_name']);

            return [
                'status' => 200,
                'message' => 'Password has been reset successfully',
                'data' => null,
            ];
        } catch (Exception $e) {
            $this->logger->error('Error completing password reset', [
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 500,
                'message' => 'Error processing request',
                'data' => null,
            ];
        }
    }

    /**
     * Count recent reset attempts for rate limiting
     */
    private function getRecentResetCount(int $userId, int $windowSeconds): int
    {
        $cutoffTime = date('Y-m-d H:i:s', time() - $windowSeconds);

        $result = $this->db->fetchOne(
            'SELECT COUNT(*) as count FROM password_reset_tokens WHERE user_id = :user_id AND created_at > :cutoff',
            [
                ':user_id' => $userId,
                ':cutoff' => $cutoffTime,
            ]
        );

        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Send password reset email (stubbed for now)
     * In production, integrate with actual email service (SendGrid, AWS SES, etc.)
     */
    private function sendResetEmail(string $email, string $firstName, string $token): void
    {
        // Stub: Log that email would be sent
        // In production: actual email service integration
        $resetUrl = "https://app.example.com/reset-password?token=$token";

        $this->logger->info('Password reset email would be sent', [
            'email' => $email,
            'reset_url' => $resetUrl,
            'first_name' => $firstName,
        ]);

        // Production example:
        // $mailer = new EmailService();
        // $mailer->send($email, 'Password Reset Request', "Hi $firstName, click here to reset: $resetUrl");
    }

    /**
     * Send password reset confirmation email
     */
    private function sendConfirmationEmail(string $email, string $firstName): void
    {
        $this->logger->info('Password reset confirmation email would be sent', [
            'email' => $email,
            'first_name' => $firstName,
        ]);

        // Production example:
        // $mailer = new EmailService();
        // $mailer->send($email, 'Password Changed', "Hi $firstName, your password has been reset.");
    }
}
