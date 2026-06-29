<?php
namespace SPLK2\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * E2E Workflow Test
 *
 * Complete user journey:
 * 1. Register new user
 * 2. Login
 * 3. Create category
 * 4. Create item in category
 * 5. Try to delete category with items (should fail)
 * 6. Delete item
 * 7. Delete category (now succeeds)
 */
class E2EWorkflowTest extends TestCase
{
    private const API_URL = 'http://localhost:8000';
    private static string $token = '';
    private static int $categoryId = 0;
    private static int $itemId = 0;
    private static ?string $testEmail = null;
    private string $email;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        // Initialize test email only once for all tests
        if (self::$testEmail === null) {
            self::$testEmail = 'e2e-test-' . time() . '-' . random_int(100000, 999999) . '@example.com';
        }
        $this->email = self::$testEmail;
    }

    /**
     * Step 1: Register new user
     */
    public function test_01_RegisterNewUser(): void
    {
        echo "\n=== STEP 1: Register New User ===\n";

        $response = $this->post('/register', [
            'email' => $this->email,
            'password' => 'TestPassword123',
            'first_name' => 'E2E',
            'last_name' => 'Tester',
        ]);

        echo "Request: POST /register\n";
        echo "Email: {$this->email}\n";
        echo "Response Status: {$response['status']}\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertGreaterThan(0, $response['data']['id']);

        echo "✓ User registered successfully (ID: {$response['data']['id']})\n";
    }

    /**
     * Step 2: Login with registered user
     */
    public function test_02_LoginUser(): void
    {
        echo "\n=== STEP 2: Login User ===\n";

        $response = $this->post('/login', [
            'email' => $this->email,
            'password' => 'TestPassword123',
        ]);

        echo "Request: POST /login\n";
        echo "Email: {$this->email}\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('token', $response['data']);

        self::$token = $response['data']['token'];
        echo "✓ Login successful\n";
        echo "Token: " . substr(self::$token, 0, 50) . "...\n";
    }

    /**
     * Step 3: Create category
     */
    public function test_03_CreateCategory(): void
    {
        echo "\n=== STEP 3: Create Category ===\n";
        echo "Authorization: Bearer " . substr(self::$token, 0, 30) . "...\n";

        $response = $this->post('/categories', [
            'name' => 'E2E Test Category',
        ]);

        echo "Request: POST /categories\n";
        echo "Response Status: {$response['status']}\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertGreaterThan(0, $response['data']['id']);

        self::$categoryId = $response['data']['id'];
        echo "✓ Category created successfully (ID: {self::$categoryId})\n";
    }

    /**
     * Step 4: Create item in category
     */
    public function test_04_CreateItem(): void
    {
        echo "\n=== STEP 4: Create Item ===\n";
        echo "Category ID: {self::$categoryId}\n";

        $response = $this->post('/items', [
            'category_id' => self::$categoryId,
            'inventory_number' => 'E2E-TEST-001',
            'status' => 'active',
        ]);

        echo "Request: POST /items\n";
        echo "Response Status: {$response['status']}\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertGreaterThan(0, $response['data']['id']);

        self::$itemId = $response['data']['id'];
        echo "✓ Item created successfully (ID: {self::$itemId})\n";
    }

    /**
     * Step 5: Try to delete category with items (should fail - business rule!)
     */
    public function test_05_TryDeleteCategoryWithItems(): void
    {
        echo "\n=== STEP 5: Try Delete Category (Should Fail) ===\n";
        echo "Category ID: {self::$categoryId}\n";
        echo "Category has {self::$itemId} item(s)\n";

        $response = $this->delete("/categories/{self::$categoryId}");

        echo "Request: DELETE /categories/{self::$categoryId}\n";
        echo "Response Status: {$response['status']}\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        // Business rule: Cannot delete category with items
        $this->assertEquals(409, $response['status']);
        $this->assertStringContainsString('Cannot delete category', $response['error']);

        echo "✓ Correctly prevented deletion (business rule enforced)\n";
        echo "  Error: {$response['error']}\n";
    }

    /**
     * Step 6: Delete item first
     */
    public function test_06_DeleteItem(): void
    {
        echo "\n=== STEP 6: Delete Item ===\n";
        echo "Item ID: {self::$itemId}\n";

        $response = $this->delete("/items/{self::$itemId}");

        echo "Request: DELETE /items/{self::$itemId}\n";
        echo "Response Status: {$response['status']}\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertEquals(200, $response['status']);
        echo "✓ Item deleted successfully\n";
    }

    /**
     * Step 7: Now delete category (should succeed)
     */
    public function test_07_DeleteCategory(): void
    {
        echo "\n=== STEP 7: Delete Category (Now Succeeds) ===\n";
        echo "Category ID: {self::$categoryId}\n";
        echo "Category is now empty\n";

        $response = $this->delete("/categories/{self::$categoryId}");

        echo "Request: DELETE /categories/{self::$categoryId}\n";
        echo "Response Status: {$response['status']}\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertEquals(200, $response['status']);
        echo "✓ Category deleted successfully\n";
    }

    /**
     * Verify everything is cleaned up
     */
    public function test_08_VerifyCleanup(): void
    {
        echo "\n=== STEP 8: Verify Cleanup ===\n";

        // Try to get deleted category (should return 404)
        $response = $this->get("/categories/{self::$categoryId}");

        echo "Request: GET /categories/{self::$categoryId}\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(404, $response['status']);
        echo "✓ Category properly deleted (not found)\n";

        // Try to get deleted item (should return 404)
        $response = $this->get("/items/{self::$itemId}");

        echo "Request: GET /items/{self::$itemId}\n";
        echo "Response Status: {$response['status']}\n";

        $this->assertEquals(404, $response['status']);
        echo "✓ Item properly deleted (not found)\n";
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function post(string $endpoint, array $data): array
    {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    private function get(string $endpoint): array
    {
        return $this->makeRequest('GET', $endpoint);
    }

    private function delete(string $endpoint): array
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = self::API_URL . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
        ];

        if (!empty(self::$token)) {
            $headers[] = "Authorization: Bearer {self::$token}";
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        if (empty($response)) {
            return [
                'status' => $httpCode,
                'message' => 'Empty response',
                'data' => null,
                'error' => $error ?: 'No response body',
            ];
        }

        return json_decode($response, true) ?: [
            'status' => $httpCode,
            'message' => 'Invalid JSON response',
            'data' => null,
            'error' => $response,
        ];
    }
}
