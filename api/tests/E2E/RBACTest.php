<?php
namespace App\Tests\E2E;

use PHPUnit\Framework\TestCase;

class RBACTest extends TestCase {
    private $baseUrl = 'http://localhost:8000';
    private $adminToken;
    private $userToken;
    private $guestToken;
    private $adminUserId;
    private $regularUserId;

    protected function setUp(): void {
        // Register test users with different roles
        // Admin user
        $adminResponse = $this->post('/register', [
            'email' => 'admin' . time() . '@test.com',
            'password' => 'Password123!',
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);
        $this->adminToken = $adminResponse['data']['token'] ?? null;
        $this->adminUserId = $adminResponse['data']['user_id'] ?? null;

        // Regular user
        $userResponse = $this->post('/register', [
            'email' => 'user' . time() . '@test.com',
            'password' => 'Password123!',
            'first_name' => 'Regular',
            'last_name' => 'User',
        ]);
        $this->userToken = $userResponse['data']['token'] ?? null;
        $this->regularUserId = $userResponse['data']['user_id'] ?? null;

        // Assign admin role to first user
        if ($this->adminUserId && $this->adminToken) {
            $this->assignRoleToUser($this->adminUserId, 1); // role_id 1 = admin
        }
    }

    /**
     * Test admin can create users
     */
    public function testAdminCanCreateUsers() {
        $response = $this->post('/users', [
            'email' => 'newuser' . time() . '@test.com',
            'password' => 'Password123!',
            'first_name' => 'New',
            'last_name' => 'User',
        ], $this->adminToken);

        $this->assertEquals(200, $response['status']);
    }

    /**
     * Test regular user cannot create users
     */
    public function testRegularUserCannotCreateUsers() {
        $response = $this->post('/users', [
            'email' => 'newuser' . time() . '@test.com',
            'password' => 'Password123!',
            'first_name' => 'New',
            'last_name' => 'User',
        ], $this->userToken);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('administrator', strtolower($response['message'] ?? ''));
    }

    /**
     * Test admin can delete users
     */
    public function testAdminCanDeleteUsers() {
        // Create a user to delete
        $createResponse = $this->post('/users', [
            'email' => 'delete' . time() . '@test.com',
            'password' => 'Password123!',
            'first_name' => 'Delete',
            'last_name' => 'Me',
        ], $this->adminToken);

        $userId = $createResponse['data']['id'] ?? null;
        $this->assertNotNull($userId);

        // Delete the user
        $response = $this->delete("/users/{$userId}", [], $this->adminToken);

        $this->assertEquals(200, $response['status']);
    }

    /**
     * Test regular user cannot delete users
     */
    public function testRegularUserCannotDeleteUsers() {
        // Create a user to delete (as admin)
        $createResponse = $this->post('/users', [
            'email' => 'nodelete' . time() . '@test.com',
            'password' => 'Password123!',
            'first_name' => 'No',
            'last_name' => 'Delete',
        ], $this->adminToken);

        $userId = $createResponse['data']['id'] ?? null;

        // Try to delete as regular user
        $response = $this->delete("/users/{$userId}", [], $this->userToken);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('administrator', strtolower($response['message'] ?? ''));
    }

    /**
     * Test users can update their own profile
     */
    public function testUserCanUpdateOwnProfile() {
        $response = $this->put("/users/{$this->regularUserId}", [
            'first_name' => 'Updated',
        ], $this->userToken);

        $this->assertEquals(200, $response['status']);
    }

    /**
     * Test users cannot update other users
     */
    public function testUserCannotUpdateOtherUsers() {
        // Try to update admin as regular user
        $response = $this->put("/users/{$this->adminUserId}", [
            'first_name' => 'Hacked',
        ], $this->userToken);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('own account', $response['message'] ?? '');
    }

    /**
     * Test admin cannot delete own account
     */
    public function testAdminCannotDeleteOwnAccount() {
        $response = $this->delete("/users/{$this->adminUserId}", [], $this->adminToken);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('own account', $response['message'] ?? '');
    }

    /**
     * Test admin can create roles
     */
    public function testAdminCanCreateRoles() {
        $response = $this->post('/roles', [
            'name' => 'moderator' . time(),
            'description' => 'Test moderator role',
        ], $this->adminToken);

        $this->assertEquals(200, $response['status']);
    }

    /**
     * Test regular user cannot create roles
     */
    public function testRegularUserCannotCreateRoles() {
        $response = $this->post('/roles', [
            'name' => 'hacker' . time(),
            'description' => 'Should fail',
        ], $this->userToken);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('administrator', strtolower($response['message'] ?? ''));
    }

    /**
     * Test admin cannot delete built-in roles
     */
    public function testAdminCannotDeleteBuiltInRoles() {
        // Try to delete admin role (id=1)
        $response = $this->delete('/roles/1', [], $this->adminToken);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('built-in', $response['message'] ?? '');
    }

    // Helper methods

    private function post($endpoint, $data, $token = null) {
        return $this->request('POST', $endpoint, $data, $token);
    }

    private function put($endpoint, $data, $token = null) {
        return $this->request('PUT', $endpoint, $data, $token);
    }

    private function delete($endpoint, $data = [], $token = null) {
        return $this->request('DELETE', $endpoint, $data, $token);
    }

    private function request($method, $endpoint, $data = [], $token = null) {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            $token ? "Authorization: Bearer {$token}" : '',
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true) ?? ['status' => $httpCode];
    }

    private function assignRoleToUser($userId, $roleId) {
        // This would typically be done via an API endpoint
        // For now, this is a placeholder for test setup
    }
}
