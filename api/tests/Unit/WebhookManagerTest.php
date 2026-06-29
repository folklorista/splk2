<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\WebhookManager;

class WebhookManagerTest extends TestCase {
    private $mockDb;
    private $mockLogger;
    private $webhookManager;

    protected function setUp(): void {
        $this->mockDb = $this->createMock(\App\Database::class);
        $this->mockLogger = $this->createMock(\App\Logger::class);
        $this->webhookManager = new WebhookManager($this->mockDb, $this->mockLogger);
    }


    /**
     * Test createWebhook fails with invalid URL
     */
    public function testCreateWebhookFailsWithInvalidUrl() {
        $result = $this->webhookManager->createWebhook(
            1,
            'not-a-valid-url',
            ['user.created']
        );

        $this->assertEquals(400, $result['status']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    /**
     * Test createWebhook fails with empty events
     */
    public function testCreateWebhookFailsWithEmptyEvents() {
        $result = $this->webhookManager->createWebhook(
            1,
            'https://example.com/webhook',
            []
        );

        $this->assertEquals(400, $result['status']);
        $this->assertStringContainsString('event', strtolower($result['message']));
    }

    /**
     * Test createWebhook fails with invalid event name
     */
    public function testCreateWebhookFailsWithInvalidEvent() {
        $result = $this->webhookManager->createWebhook(
            1,
            'https://example.com/webhook',
            ['invalid.event']
        );

        $this->assertEquals(400, $result['status']);
        $this->assertStringContainsString('Invalid event', $result['message']);
    }

    /**
     * Test updateWebhook successfully updates
     */
    public function testUpdateWebhookSuccessfully() {
        // Mock get webhook
        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => [
                'id' => 1,
                'url' => 'https://old.com/webhook',
                'events' => json_encode(['user.created']),
            ],
        ]);

        $this->mockDb->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('UPDATE webhooks'));

        $result = $this->webhookManager->updateWebhook(1, [
            'url' => 'https://new.com/webhook',
        ]);

        $this->assertEquals(200, $result['status']);
    }

    /**
     * Test updateWebhook fails when webhook not found
     */
    public function testUpdateWebhookFailsWhenNotFound() {
        $this->mockDb->method('get')->willReturn([
            'status' => 404,
            'data' => null,
        ]);

        $result = $this->webhookManager->updateWebhook(999, ['url' => 'https://example.com/webhook']);

        $this->assertEquals(404, $result['status']);
    }

    /**
     * Test deleteWebhook successfully deletes
     */
    public function testDeleteWebhookSuccessfully() {
        $this->mockDb->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('DELETE FROM webhooks'));

        $result = $this->webhookManager->deleteWebhook(1);

        $this->assertEquals(200, $result['status']);
    }

    /**
     * Test getWebhook retrieves webhook
     */
    public function testGetWebhookSuccessfully() {
        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => [
                'id' => 1,
                'url' => 'https://example.com/webhook',
                'events' => json_encode(['user.created']),
            ],
        ]);

        $result = $this->webhookManager->getWebhook(1);

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['data']['events']);
    }

    /**
     * Test getUserWebhooks retrieves user webhooks
     */
    public function testGetUserWebhooksSuccessfully() {
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                [
                    'id' => 1,
                    'url' => 'https://example.com/webhook',
                    'events' => json_encode(['user.created']),
                ],
            ],
        ]);

        $result = $this->webhookManager->getUserWebhooks(1);

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertCount(1, $result['data']);
    }

    /**
     * Test testWebhook fails when webhook not found
     */
    public function testTestWebhookFailsWhenNotFound() {
        $this->mockDb->method('get')->willReturn([
            'status' => 404,
            'data' => null,
        ]);

        $result = $this->webhookManager->testWebhook(999);

        $this->assertEquals(404, $result['status']);
    }

    /**
     * Test triggerEvent with empty webhooks
     */
    public function testTriggerEventWithNoWebhooks() {
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [],
        ]);

        // This should not throw even with no webhooks
        $this->webhookManager->triggerEvent('user.created', 1, ['test' => 'data']);

        // If we got here, triggering worked without error
        $this->assertTrue(true);
    }
}
