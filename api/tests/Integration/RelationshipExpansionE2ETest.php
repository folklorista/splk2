<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class RelationshipExpansionE2ETest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000/api/v1';
    private string $email = '';
    private string $password = 'TestPassword123!';
    private string $accessToken = '';
    private int $userId = 0;

    protected function setUp(): void
    {
        // Create unique email for test
        $this->email = 'relationship-' . time() . '-' . rand(1000, 9999) . '@example.com';

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
            'first_name' => 'Relationship',
            'last_name' => 'Test',
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
     * Test GET without include returns record without relationships
     */
    public function testGetWithoutInclude()
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
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
        // Should not have relationships loaded
        $this->assertArrayNotHasKey('roles', $data['data']);
    }

    /**
     * Test GET with invalid include parameter returns 400
     */
    public function testGetWithInvalidInclude()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?include=invalid;drop";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(400, $data['status']);
        $this->assertStringContainsString('include', strtolower($data['message']));
    }

    /**
     * Test GET with include parameter parses correctly
     */
    public function testGetWithIncludeParameter()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?include=roles";
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
        // Should have roles (even if empty)
        $this->assertArrayHasKey('roles', $data['data']);
        $this->assertIsArray($data['data']['roles']);
    }

    /**
     * Test GET list with include parameter
     */
    public function testListWithIncludeParameter()
    {
        $url = "{$this->baseUrl}/users?include=roles";
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

        // Each record should have roles
        if (!empty($data['data'])) {
            foreach ($data['data'] as $record) {
                $this->assertArrayHasKey('roles', $record);
                $this->assertIsArray($record['roles']);
            }
        }
    }

    /**
     * Test GET with multiple includes
     */
    public function testGetWithMultipleIncludes()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?include=roles,groups";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        $this->assertArrayHasKey('roles', $data['data']);
        $this->assertArrayHasKey('groups', $data['data']);
        $this->assertIsArray($data['data']['roles']);
        $this->assertIsArray($data['data']['groups']);
    }

    /**
     * Test GET with include and field selection combined
     */
    public function testGetWithIncludeAndFieldSelection()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?include=roles&fields=id,email,roles";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        // Should have selected fields
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
        // Should not have other fields like first_name
        $this->assertArrayNotHasKey('first_name', $data['data']);
        // Should have relationships
        $this->assertArrayHasKey('roles', $data['data']);
    }

    /**
     * Test GET with include using whitespace
     */
    public function testGetWithIncludeWhitespace()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?include=roles%2C+groups";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        $this->assertEquals(200, $data['status']);
        $this->assertArrayHasKey('roles', $data['data']);
        $this->assertArrayHasKey('groups', $data['data']);
    }

    /**
     * Test GET with empty include parameter
     */
    public function testGetWithEmptyInclude()
    {
        $url = "{$this->baseUrl}/users/{$this->userId}?include=";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Should succeed and work like normal
        $this->assertEquals(200, $data['status']);
        $this->assertArrayHasKey('id', $data['data']);
    }
}
