<?php
namespace App;

class WebhookManager {
    private $db;
    private $logger;

    public function __construct(Database $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Create a new webhook
     *
     * @param int $userId User creating the webhook
     * @param string $url Target URL
     * @param array $events Event names to subscribe to
     * @param bool $active Webhook active status
     * @return array
     */
    public function createWebhook(int $userId, string $url, array $events, bool $active = true): array {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return [
                    'status' => 400,
                    'message' => 'Invalid webhook URL',
                    'data' => null,
                ];
            }

            // Validate events
            if (empty($events) || !is_array($events)) {
                return [
                    'status' => 400,
                    'message' => 'At least one event must be specified',
                    'data' => null,
                ];
            }

            // Validate event names (should be table.action format)
            $validEvents = $this->getValidEvents();
            foreach ($events as $event) {
                if (!in_array($event, $validEvents)) {
                    return [
                        'status' => 400,
                        'message' => "Invalid event: {$event}",
                        'data' => null,
                    ];
                }
            }

            // Insert webhook
            $this->db->execute(
                'INSERT INTO webhooks (user_id, url, events, active) VALUES (?, ?, ?, ?)',
                [$userId, $url, json_encode($events), $active ? 1 : 0]
            );

            $webhookId = $this->db->lastInsertId();

            $this->logger->info('Webhook created', [
                'webhook_id' => $webhookId,
                'user_id' => $userId,
                'url' => $url,
                'events' => $events,
            ]);

            return [
                'status' => 200,
                'message' => 'Webhook created successfully',
                'data' => ['webhook_id' => $webhookId],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error creating webhook', [
                'user_id' => $userId,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error creating webhook',
                'data' => null,
            ];
        }
    }

    /**
     * Update webhook configuration
     *
     * @param int $webhookId
     * @param array $data Fields to update (url, events, active)
     * @return array
     */
    public function updateWebhook(int $webhookId, array $data): array {
        try {
            // Get existing webhook
            $webhookResult = $this->db->get('webhooks', $webhookId);
            if ($webhookResult['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'Webhook not found',
                    'data' => null,
                ];
            }

            // Validate URL if provided
            if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
                return [
                    'status' => 400,
                    'message' => 'Invalid webhook URL',
                    'data' => null,
                ];
            }

            // Validate events if provided
            if (isset($data['events'])) {
                if (!is_array($data['events']) || empty($data['events'])) {
                    return [
                        'status' => 400,
                        'message' => 'At least one event must be specified',
                        'data' => null,
                    ];
                }

                $validEvents = $this->getValidEvents();
                foreach ($data['events'] as $event) {
                    if (!in_array($event, $validEvents)) {
                        return [
                            'status' => 400,
                            'message' => "Invalid event: {$event}",
                            'data' => null,
                        ];
                    }
                }

                $data['events'] = json_encode($data['events']);
            }

            // Build update query
            $updates = [];
            $values = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['url', 'events', 'active', 'retry_count', 'timeout_seconds'])) {
                    $updates[] = "`{$key}` = ?";
                    $values[] = $value;
                }
            }

            if (empty($updates)) {
                return [
                    'status' => 400,
                    'message' => 'No valid fields to update',
                    'data' => null,
                ];
            }

            $values[] = $webhookId;
            $sql = 'UPDATE webhooks SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $this->db->execute($sql, $values);

            $this->logger->info('Webhook updated', [
                'webhook_id' => $webhookId,
                'updated_fields' => array_keys($data),
            ]);

            return [
                'status' => 200,
                'message' => 'Webhook updated successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error updating webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error updating webhook',
                'data' => null,
            ];
        }
    }

    /**
     * Delete webhook
     *
     * @param int $webhookId
     * @return array
     */
    public function deleteWebhook(int $webhookId): array {
        try {
            $result = $this->db->execute('DELETE FROM webhooks WHERE id = ?', [$webhookId]);

            $this->logger->info('Webhook deleted', ['webhook_id' => $webhookId]);

            return [
                'status' => 200,
                'message' => 'Webhook deleted successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error deleting webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error deleting webhook',
                'data' => null,
            ];
        }
    }

    /**
     * Trigger webhook event (send payload to all subscribed webhooks)
     *
     * @param string $event Event name (table.action format)
     * @param int $recordId Record ID that triggered the event
     * @param array $payload Event payload data
     */
    public function triggerEvent(string $event, int $recordId, array $payload): void {
        try {
            // Get all active webhooks subscribed to this event
            $webhooksResult = $this->db->getAllWhere('webhooks', 'active = 1', []);

            if ($webhooksResult['status'] !== 200 || empty($webhooksResult['data'])) {
                return;
            }

            foreach ($webhooksResult['data'] as $webhook) {
                $events = json_decode($webhook['events'], true) ?? [];

                // Check if webhook is subscribed to this event
                if (!in_array($event, $events) && !in_array('*', $events)) {
                    continue;
                }

                // Send webhook asynchronously (queue for background processing)
                $this->queueWebhookDelivery($webhook['id'], $event, $payload);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error triggering webhook event', [
                'event' => $event,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue webhook for delivery (in real implementation, this would be a message queue)
     * For now, we'll attempt delivery synchronously with retry logic
     *
     * @param int $webhookId
     * @param string $event
     * @param array $payload
     */
    private function queueWebhookDelivery(int $webhookId, string $event, array $payload): void {
        try {
            $webhookResult = $this->db->get('webhooks', $webhookId);
            if ($webhookResult['status'] !== 200) {
                return;
            }

            $webhook = $webhookResult['data'];
            $url = $webhook['url'];
            $retryCount = $webhook['retry_count'] ?? 3;
            $timeout = $webhook['timeout_seconds'] ?? 30;

            // Create log entry
            $logPayload = [
                'event' => $event,
                'timestamp' => date('c'),
                'data' => $payload,
            ];

            $logId = $this->createWebhookLog($webhookId, $event, $logPayload);

            // Attempt delivery with retries
            $delivered = $this->deliverWebhook($url, $logPayload, $timeout, $retryCount);

            if ($delivered) {
                $this->db->execute(
                    'UPDATE webhook_logs SET delivered_at = NOW() WHERE id = ?',
                    [$logId]
                );

                $this->logger->info('Webhook delivered', [
                    'webhook_id' => $webhookId,
                    'event' => $event,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error queueing webhook delivery', [
                'webhook_id' => $webhookId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create webhook log entry
     *
     * @param int $webhookId
     * @param string $event
     * @param array $payload
     * @return int Log ID
     */
    private function createWebhookLog(int $webhookId, string $event, array $payload): int {
        $this->db->execute(
            'INSERT INTO webhook_logs (webhook_id, event, payload) VALUES (?, ?, ?)',
            [$webhookId, $event, json_encode($payload)]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Deliver webhook with retry logic
     *
     * @param string $url
     * @param array $payload
     * @param int $timeout
     * @param int $maxRetries
     * @return bool Success
     */
    private function deliverWebhook(string $url, array $payload, int $timeout, int $maxRetries): bool {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'User-Agent: SPLK2-Webhook/1.0',
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                // Success if 2xx status code
                if ($statusCode >= 200 && $statusCode < 300) {
                    return true;
                }

                // Don't retry 4xx errors (client errors)
                if ($statusCode >= 400 && $statusCode < 500) {
                    $this->logger->warning('Webhook delivery failed with client error', [
                        'url' => $url,
                        'status_code' => $statusCode,
                        'response' => $response,
                    ]);
                    return false;
                }

                // Retry on 5xx or network errors
                if ($attempt < $maxRetries) {
                    sleep(min(2 ** ($attempt - 1), 32)); // Exponential backoff
                }
            } catch (\Exception $e) {
                if ($attempt < $maxRetries) {
                    sleep(min(2 ** ($attempt - 1), 32));
                }
            }
        }

        return false;
    }

    /**
     * Test webhook by sending test payload
     *
     * @param int $webhookId
     * @return array
     */
    public function testWebhook(int $webhookId): array {
        try {
            $webhookResult = $this->db->get('webhooks', $webhookId);
            if ($webhookResult['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'Webhook not found',
                    'data' => null,
                ];
            }

            $webhook = $webhookResult['data'];
            $testPayload = [
                'event' => 'test.webhook',
                'timestamp' => date('c'),
                'data' => [
                    'test' => true,
                    'message' => 'This is a test webhook delivery',
                ],
            ];

            $url = $webhook['url'];
            $timeout = $webhook['timeout_seconds'] ?? 30;

            // Create log for test
            $logId = $this->createWebhookLog($webhookId, 'test.webhook', $testPayload);

            // Attempt delivery
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: SPLK2-Webhook/1.0',
                'X-Webhook-Test: true',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $success = $statusCode >= 200 && $statusCode < 300;

            if ($success) {
                $this->db->execute(
                    'UPDATE webhook_logs SET status_code = ?, response_body = ?, delivered_at = NOW() WHERE id = ?',
                    [$statusCode, $response, $logId]
                );
            } else {
                $this->db->execute(
                    'UPDATE webhook_logs SET status_code = ?, response_body = ? WHERE id = ?',
                    [$statusCode, $error ?: $response, $logId]
                );
            }

            return [
                'status' => 200,
                'message' => $success ? 'Webhook test successful' : 'Webhook test failed',
                'data' => [
                    'status_code' => $statusCode,
                    'response' => $response,
                    'success' => $success,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error testing webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error testing webhook',
                'data' => null,
            ];
        }
    }

    /**
     * Get list of valid events
     *
     * @return array
     */
    private function getValidEvents(): array {
        return [
            'user.created',
            'user.updated',
            'user.deleted',
            'item.created',
            'item.updated',
            'item.deleted',
            'category.created',
            'category.updated',
            'category.deleted',
            'group.created',
            'group.updated',
            'group.deleted',
            'person.created',
            'person.updated',
            'person.deleted',
            'loan.created',
            'loan.updated',
            'loan.deleted',
            'place.created',
            'place.updated',
            'place.deleted',
            'event.created',
            'event.updated',
            'event.deleted',
            '*', // Subscribe to all events
        ];
    }

    /**
     * Get webhook details
     *
     * @param int $webhookId
     * @return array
     */
    public function getWebhook(int $webhookId): array {
        try {
            $result = $this->db->get('webhooks', $webhookId);

            if ($result['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'Webhook not found',
                    'data' => null,
                ];
            }

            $webhook = $result['data'];
            $webhook['events'] = json_decode($webhook['events'], true);

            return [
                'status' => 200,
                'message' => 'Webhook retrieved',
                'data' => $webhook,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error retrieving webhook',
                'data' => null,
            ];
        }
    }

    /**
     * Get all webhooks for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserWebhooks(int $userId): array {
        try {
            $result = $this->db->getAllWhere('webhooks', 'user_id = ?', [$userId]);

            if ($result['status'] !== 200) {
                return [
                    'status' => 200,
                    'message' => 'No webhooks found',
                    'data' => [],
                ];
            }

            foreach ($result['data'] as &$webhook) {
                $webhook['events'] = json_decode($webhook['events'], true);
            }

            return [
                'status' => 200,
                'message' => 'Webhooks retrieved',
                'data' => $result['data'],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error retrieving webhooks',
                'data' => null,
            ];
        }
    }
}
