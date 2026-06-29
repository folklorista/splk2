<?php
namespace SPLK2\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Change Tracking E2E Test
 *
 * Verify that audit logs capture:
 * 1. new_values on CREATE
 * 2. old_values and new_values on UPDATE
 * 3. old_values on DELETE
 */
class ChangeTrackingE2ETest extends TestCase
{
    private const API_URL = 'http://localhost:8000';
    private static string $token = '';
    private static int $categoryId = 0;
    private static ?string $testEmail = null;
    private string $email;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        if (self::$testEmail === null) {
            self::$testEmail = 'change-tracking-' . time() . '-' . random_int(100000, 999999) . '@example.com';
        }
        $this->email = self::$testEmail;
    }

    /**
     * Step 1: Register user for tracking
     */
    public function test_01_RegisterUser(): void
    {
        echo "\n=== STEP 1: Register User for Change Tracking ===\n";

        $response = $this->post('/register', [
            'email' => $this->email,
            'password' => 'TrackingTest123',
            'first_name' => 'Change',
            'last_name' => 'Tracker',
        ]);

        echo "Email: {$this->email}\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(201, $response['status']);
        echo "✓ User registered\n";
    }

    /**
     * Step 2: Login to get token
     */
    public function test_02_LoginUser(): void
    {
        echo "\n=== STEP 2: Login User ===\n";

        $response = $this->post('/login', [
            'email' => $this->email,
            'password' => 'TrackingTest123',
        ]);

        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('token', $response['data']);

        self::$token = $response['data']['token'];
        echo "✓ Login successful\n";
    }

    /**
     * Step 3: Create category and verify new_values in audit log
     */
    public function test_03_CreateCategoryWithChangeTracking(): void
    {
        echo "\n=== STEP 3: Create Category (Check new_values) ===\n";

        $createData = [
            'name' => 'Change Tracking Test Category',
        ];

        $response = $this->post('/categories', $createData);

        echo "Request: POST /categories\n";
        echo "Data: " . json_encode($createData) . "\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);

        self::$categoryId = $response['data']['id'];
        echo "✓ Category created (ID: {$this->getCategoryId()})\n";

        // Wait for audit log to be written
        sleep(1);

        // Fetch audit log for this CREATE action
        $auditResponse = $this->getAuditLogs('categories', $this->getCategoryId(), 'CREATE');

        echo "\nAudit Log Response:\n";
        echo json_encode($auditResponse, JSON_PRETTY_PRINT) . "\n";

        $this->assertArrayHasKey('data', $auditResponse);
        $this->assertNotEmpty($auditResponse['data']);

        $auditLog = $auditResponse['data'][0];

        // Verify new_values exists and contains the created data
        $this->assertArrayHasKey('new_values', $auditLog);
        $this->assertNotNull($auditLog['new_values']);

        $newValues = json_decode($auditLog['new_values'], true);
        $this->assertIsArray($newValues);
        $this->assertEquals('Change Tracking Test Category', $newValues['name']);

        // For CREATE, old_values should be NULL
        $this->assertNull($auditLog['old_values']);

        echo "✓ Audit log verified:\n";
        echo "  - new_values: " . $auditLog['new_values'] . "\n";
        echo "  - old_values: NULL\n";
    }

    /**
     * Step 4: Update category and verify old_values + new_values
     */
    public function test_04_UpdateCategoryWithChangeTracking(): void
    {
        echo "\n=== STEP 4: Update Category (Check old_values + new_values) ===\n";

        $originalName = 'Change Tracking Test Category';
        $newName = 'Updated Change Tracking Category';

        $updateData = [
            'name' => $newName,
        ];

        $response = $this->put("/categories/{$this->getCategoryId()}", $updateData);

        echo "Request: PUT /categories/{$this->getCategoryId()}\n";
        echo "Data: " . json_encode($updateData) . "\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(200, $response['status']);
        echo "✓ Category updated\n";

        // Wait for audit log
        sleep(1);

        // Fetch audit log for UPDATE action
        $auditResponse = $this->getAuditLogs('categories', $this->getCategoryId(), 'UPDATE');

        echo "\nAudit Log Response:\n";
        echo json_encode($auditResponse, JSON_PRETTY_PRINT) . "\n";

        $this->assertNotEmpty($auditResponse['data']);
        $auditLog = $auditResponse['data'][0];

        // Verify old_values
        $this->assertArrayHasKey('old_values', $auditLog);
        $this->assertNotNull($auditLog['old_values']);

        $oldValues = json_decode($auditLog['old_values'], true);
        $this->assertIsArray($oldValues);
        $this->assertEquals($originalName, $oldValues['name']);

        // Verify new_values
        $this->assertArrayHasKey('new_values', $auditLog);
        $this->assertNotNull($auditLog['new_values']);

        $newValues = json_decode($auditLog['new_values'], true);
        $this->assertIsArray($newValues);
        $this->assertEquals($newName, $newValues['name']);

        echo "✓ Audit log verified:\n";
        echo "  - old_values: " . $auditLog['old_values'] . "\n";
        echo "  - new_values: " . $auditLog['new_values'] . "\n";
    }

    /**
     * Step 5: Delete category and verify old_values
     */
    public function test_05_DeleteCategoryWithChangeTracking(): void
    {
        echo "\n=== STEP 5: Delete Category (Check old_values) ===\n";

        $response = $this->delete("/categories/{$this->getCategoryId()}");

        echo "Request: DELETE /categories/{$this->getCategoryId()}\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(200, $response['status']);
        echo "✓ Category deleted\n";

        // Wait for audit log
        sleep(1);

        // Fetch audit log for DELETE action
        $auditResponse = $this->getAuditLogs('categories', $this->getCategoryId(), 'DELETE');

        echo "\nAudit Log Response:\n";
        echo json_encode($auditResponse, JSON_PRETTY_PRINT) . "\n";

        $this->assertNotEmpty($auditResponse['data']);
        $auditLog = $auditResponse['data'][0];

        // Verify old_values contains the deleted data
        $this->assertArrayHasKey('old_values', $auditLog);
        $this->assertNotNull($auditLog['old_values']);

        $oldValues = json_decode($auditLog['old_values'], true);
        $this->assertIsArray($oldValues);
        $this->assertEquals('Updated Change Tracking Category', $oldValues['name']);

        // For DELETE, new_values should be NULL
        $this->assertNull($auditLog['new_values']);

        echo "✓ Audit log verified:\n";
        echo "  - old_values: " . $auditLog['old_values'] . "\n";
        echo "  - new_values: NULL\n";
    }

    /**
     * Summary: Verify all three audit entries
     */
    public function test_06_VerifyFullAuditTrail(): void
    {
        echo "\n=== STEP 6: Verify Full Audit Trail ===\n";

        // Fetch all audit logs for this category
        $allAudits = $this->getAllAuditLogs('categories', $this->getCategoryId());

        echo "Total audit entries: " . count($allAudits['data']) . "\n";

        $this->assertGreaterThanOrEqual(3, count($allAudits['data']),
            "Should have at least 3 audit entries (CREATE, UPDATE, DELETE)");

        // Verify we have all three actions
        $actions = array_map(fn($log) => $log['details'], $allAudits['data']);

        echo "\nAudit Actions Found:\n";
        foreach ($actions as $action) {
            echo "  - $action\n";
        }

        $hasCreate = in_array('Record created', $actions);
        $hasUpdate = in_array('Record updated', $actions);
        $hasDelete = in_array('Record deleted', $actions);

        $this->assertTrue($hasCreate, "Should have CREATE action");
        $this->assertTrue($hasUpdate, "Should have UPDATE action");
        $this->assertTrue($hasDelete, "Should have DELETE action");

        echo "\n✓ Full audit trail verified!\n";
        echo "  - CREATE with new_values ✓\n";
        echo "  - UPDATE with old_values + new_values ✓\n";
        echo "  - DELETE with old_values ✓\n";
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function post(string $endpoint, array $data): array
    {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    private function put(string $endpoint, array $data): array
    {
        return $this->makeRequest('PUT', $endpoint, $data);
    }

    private function get(string $endpoint): array
    {
        return $this->makeRequest('GET', $endpoint);
    }

    private function delete(string $endpoint): array
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    private function getAuditLogs(string $tableName, int $recordId, string $action): array
    {
        $endpoint = "/audit_logs?table_name={$tableName}&record_id={$recordId}";
        return $this->makeRequest('GET', $endpoint);
    }

    private function getAllAuditLogs(string $tableName, int $recordId): array
    {
        $endpoint = "/audit_logs?table_name={$tableName}&record_id={$recordId}";
        return $this->makeRequest('GET', $endpoint);
    }

    private function makeRequest(string $method, string $endpoint, ?array $body = null): array
    {
        $url = self::API_URL . $endpoint;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $headers = ['Content-Type: application/json'];
        if (self::$token) {
            $headers[] = 'Authorization: Bearer ' . self::$token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return [
            'status' => $httpCode,
            'data' => $decoded['data'] ?? null,
            'message' => $decoded['message'] ?? null,
            'error' => $decoded['error'] ?? null,
        ];
    }

    private function getCategoryId(): int
    {
        return self::$categoryId;
    }
}
