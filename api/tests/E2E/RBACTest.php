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
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;

    protected function setUp(): void {
        $this->dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1:3306';
        $this->dbUser = $_ENV['DB_USERNAME'] ?? 'root';
        $this->dbPass = $_ENV['DB_PASSWORD'] ?? 'root';
        $this->dbName = $_ENV['DB_NAME'] ?? 'splk';
        // Register test users with different roles
        // Admin user
        $adminEmail = 'admin' . time() . '@test.com';
        $adminPassword = 'Password123!';
        $adminRegResponse = $this->post('/register', [
            'email' => $adminEmail,
            'password' => $adminPassword,
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);
        $this->adminUserId = $adminRegResponse['data']['id'] ?? null;

        // Login as admin to get token
        if ($this->adminUserId) {
            $adminLoginResponse = $this->post('/login', [
                'email' => $adminEmail,
                'password' => $adminPassword,
            ]);
            $this->adminToken = $adminLoginResponse['data']['accessToken'] ?? null;
        }

        // Regular user
        $userEmail = 'user' . time() . '@test.com';
        $userPassword = 'Password123!';
        $userRegResponse = $this->post('/register', [
            'email' => $userEmail,
            'password' => $userPassword,
            'first_name' => 'Regular',
            'last_name' => 'User',
        ]);
        $this->regularUserId = $userRegResponse['data']['id'] ?? null;

        // Login as regular user to get token
        if ($this->regularUserId) {
            $userLoginResponse = $this->post('/login', [
                'email' => $userEmail,
                'password' => $userPassword,
            ]);
            $this->userToken = $userLoginResponse['data']['accessToken'] ?? null;
        }

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

        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true) ?? ['status' => $httpCode];
    }

    private function assignRoleToUser($userId, $roleId) {
        try {
            $host = explode(':', $this->dbHost)[0];
            $port = explode(':', $this->dbHost)[1] ?? 3306;

            $conn = new \mysqli($host, $this->dbUser, $this->dbPass, $this->dbName, $port);
            if ($conn->connect_error) {
                return ['status' => 500, 'message' => 'Database connection failed'];
            }

            $stmt = $conn->prepare("INSERT INTO users_roles (user_id, role_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE role_id = ?");
            $stmt->bind_param("iii", $userId, $roleId, $roleId);

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                return ['status' => 200, 'message' => 'Role assigned successfully'];
            } else {
                $error = $stmt->error;
                $stmt->close();
                $conn->close();
                return ['status' => 500, 'message' => 'Failed to assign role: ' . $error];
            }
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
