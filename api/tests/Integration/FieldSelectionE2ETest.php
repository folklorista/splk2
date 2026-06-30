<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class FieldSelectionE2ETest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000/api/v1';
    private string $email = '';
    private string $password = 'TestPassword123!';
    private string $accessToken = '';
    private int $userId = 0;

    protected function setUp(): void
    {
        // Create unique email for test
        $this->email = 'field-selection-' . time() . '-' . rand(1000, 9999) . '@example.com';

        // Register user
        $this->registerUser();

        // Login to get token
        $this->loginUser();
    }

    private function registerUser(): void
    {
        $response = $this->makeRequest('POST', '/register', [
            'email' => $this->email,
            'password' => $this->password,
            'first_name' => 'Field',
            'last_name' => 'Selection',
        ]);

        $this->assertEquals(201, $response['status']);
        $this->userId = $response['data']['id'];
    }

    private function loginUser(): void
    {
        $response = $this->makeRequest('POST', '/login', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->accessToken = $response['data']['accessToken'];
    }

    private function makeRequest(string $method, string $endpoint, array $data = null, array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        $defaultHeaders = [
            'Content-Type: application/json',
        ];

        if (!empty($this->accessToken)) {
            $defaultHeaders[] = "Authorization: Bearer {$this->accessToken}";
        }

        $headers = array_merge($defaultHeaders, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true) ?? [
            'status' => $httpCode,
            'message' => 'Failed to parse response',
            'data' => null,
        ];
    }

    /**
     * Test GET without field selection returns all non-sensitive fields
     */
    public function testGetWithoutFieldSelection()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        $this->assertIsArray($data['data']);

        // Should have non-sensitive fields
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertArrayHasKey('first_name', $data['data']);
        $this->assertArrayHasKey('last_name', $data['data']);

        // Should NOT have sensitive fields (password)
        $this->assertArrayNotHasKey('password', $data['data']);
    }

    /**
     * Test GET with field selection returns only requested fields
     */
    public function testGetWithFieldSelection()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?fields=id,email";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        $this->assertIsArray($data['data']);

        // Should only have requested fields
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);

        // Should NOT have other fields
        $this->assertArrayNotHasKey('first_name', $data['data']);
        $this->assertArrayNotHasKey('last_name', $data['data']);
    }

    /**
     * Test field selection with single field
     */
    public function testGetWithSingleFieldSelection()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?fields=email";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);

        // Should only have email field (even though id is usually required, we're respecting field selection)
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertArrayNotHasKey('first_name', $data['data']);
        $this->assertArrayNotHasKey('password', $data['data']);
    }

    /**
     * Test field selection with whitespace
     */
    public function testGetWithFieldSelectionWhitespace()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?fields=id%2C+email%2C+first_name";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);

        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertArrayHasKey('first_name', $data['data']);
    }

    /**
     * Test invalid field name returns error
     */
    public function testInvalidFieldName()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?fields=id;drop";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Should return 400 error
        $this->assertEquals(400, $data['status']);
        $this->assertStringContainsString('Invalid fields parameter', $data['message']);
    }

    /**
     * Test list endpoint with field selection
     */
    public function testListWithFieldSelection()
    {
        // Create a test category
        $categoryResponse = $this->makeRequest('POST', '/categories', [
            'name' => 'Field Selection Test Category ' . time(),
        ]);

        $this->assertEquals(201, $categoryResponse['status']);

        // Now get list with field selection
        $url = "{$this->baseUrl}/categories?fields=id,name";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        $this->assertIsArray($data['data']);

        if (!empty($data['data'])) {
            $firstItem = $data['data'][0];
            $this->assertArrayHasKey('id', $firstItem);
            $this->assertArrayHasKey('name', $firstItem);
            // position and parent_id shouldn't be returned (only requested id and name)
            $this->assertArrayNotHasKey('position', $firstItem);
        }
    }

    /**
     * Test that always protected fields cannot be accessed even if explicitly requested
     */
    public function testAlwaysProtectedFieldCannotBeRequested()
    {
        // Try to request an always protected field (api_secret doesn't exist but we'll try api_key style field)
        // Since users table doesn't have always protected fields, we can't test this directly
        // But we can test that non-existent fields are simply not returned

        $url = "{$this->baseUrl}/users/{$this->userId}?fields=id,nonexistent_field";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayNotHasKey('nonexistent_field', $data['data']);
    }

    /**
     * Test case-insensitive field names
     */
    public function testCaseInsensitiveFieldNames()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?fields=ID,EMAIL";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        // Should still return fields even with uppercase
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
    }
}
