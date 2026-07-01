<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class BulkOperationsE2ETest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000/api/v1';
    private string $email = '';
    private string $password = 'TestPassword123!';
    private string $accessToken = '';
    private int $userId = 0;
    private int $categoryId = 0;

    protected function setUp(): void
    {
        $this->email = 'bulk-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $this->userId = $this->registerUser();
        $this->makeAdmin($this->userId);
        $this->loginUser();
        $this->categoryId = $this->createCategory('BulkTest Category ' . time() . '-' . rand(1000, 9999));
    }

    private function registerUser(?string $email = null, ?string $password = null): int
    {
        $response = $this->makeRequest('POST', '/register', [
            'email' => $email ?? $this->email,
            'password' => $password ?? $this->password,
            'first_name' => 'Bulk',
            'last_name' => 'Test',
        ]);

        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    /**
     * Grant the admin role directly in the DB, same rationale as SortingE2ETest:
     * 'user' role read access on 'items' is filtered by an owner column that
     * plain registered users can't satisfy without an explicit created_by.
     */
    private function makeAdmin(int $userId): void
    {
        [$host, $port] = array_pad(explode(':', getenv('DB_HOST') ?: '127.0.0.1:3306'), 2, '3306');
        $pdo = new \PDO(
            "mysql:host={$host};port={$port};dbname=" . getenv('DB_NAME'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        $stmt = $pdo->prepare('INSERT INTO users_roles (user_id, role_id) VALUES (:user_id, 1)');
        $stmt->execute(['user_id' => $userId]);
    }

    private function loginUser(?string $email = null, ?string $password = null): string
    {
        $response = $this->makeRequest('POST', '/login', [
            'email' => $email ?? $this->email,
            'password' => $password ?? $this->password,
        ]);

        $this->assertEquals(200, $response['status']);
        $token = $response['data']['accessToken'];
        if ($email === null) {
            $this->accessToken = $token;
        }
        return $token;
    }

    private function makeRequest(string $method, string $endpoint, array $data = null, array $headers = [], ?string $token = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        $activeToken = $token ?? $this->accessToken;
        $defaultHeaders = ['Content-Type: application/json'];
        if (!empty($activeToken)) {
            $defaultHeaders[] = "Authorization: Bearer {$activeToken}";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? ['status' => 0, 'data' => null];
    }

    private function createCategory(string $name): int
    {
        $response = $this->makeRequest('POST', '/categories', ['name' => $name]);
        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    private function itemPayload(string $suffix, string $status = 'active'): array
    {
        return [
            'category_id' => $this->categoryId,
            'inventory_number' => 'bulktest-' . $suffix . '-' . rand(1000, 9999),
            'status' => $status,
        ];
    }

    public function testBulkCreateItemsSucceeds()
    {
        $items = [
            $this->itemPayload('create-a'),
            $this->itemPayload('create-b'),
            $this->itemPayload('create-c'),
        ];

        $response = $this->makeRequest('POST', '/items/bulk', $items);

        $this->assertEquals(201, $response['status']);
        $this->assertCount(3, $response['data']['created']);
        $this->assertEmpty($response['data']['errors']);

        foreach ($response['data']['created'] as $id) {
            $getResponse = $this->makeRequest('GET', "/items/{$id}");
            $this->assertEquals(200, $getResponse['status']);
        }
    }

    public function testBulkCreatePartiallyInvalidRecordReturns207()
    {
        $items = [
            $this->itemPayload('partial-ok'),
            ['inventory_number' => 'bulktest-partial-missing-category-' . rand(1000, 9999)], // missing required category_id
        ];

        $response = $this->makeRequest('POST', '/items/bulk', $items);

        $this->assertEquals(207, $response['status']);
        $this->assertCount(1, $response['data']['created']);
        $this->assertCount(1, $response['data']['errors']);
        $this->assertEquals(1, $response['data']['errors'][0]['index']);
    }

    public function testBulkCreateRequiresNonEmptyArray()
    {
        $response = $this->makeRequest('POST', '/items/bulk', ['not' => 'an array of records']);
        $this->assertEquals(400, $response['status']);
    }

    public function testBulkCreateOverLimitReturns400()
    {
        // Limit check happens before per-record validation, so record content doesn't matter here.
        $items = array_fill(0, 101, ['x' => 'y']);

        $response = $this->makeRequest('POST', '/items/bulk', $items);

        $this->assertEquals(400, $response['status']);
    }

    public function testBulkUpdateItemsSucceeds()
    {
        $created = $this->makeRequest('POST', '/items/bulk', [
            $this->itemPayload('update-a'),
            $this->itemPayload('update-b'),
        ]);
        $this->assertEquals(201, $created['status']);
        [$idA, $idB] = $created['data']['created'];

        $response = $this->makeRequest('PUT', '/items/bulk', [
            ['id' => $idA, 'status' => 'repair'],
            ['id' => $idB, 'status' => 'retired'],
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertEqualsCanonicalizing([$idA, $idB], $response['data']['updated']);
        $this->assertEmpty($response['data']['errors']);

        $getA = $this->makeRequest('GET', "/items/{$idA}");
        $this->assertEquals('repair', $getA['data']['status']);
    }

    public function testBulkUpdateNonExistentIdReturnsErrorEntry()
    {
        $created = $this->makeRequest('POST', '/items/bulk', [$this->itemPayload('update-mixed')]);
        $validId = $created['data']['created'][0];
        $bogusId = 999999999;

        $response = $this->makeRequest('PUT', '/items/bulk', [
            ['id' => $validId, 'status' => 'storage'],
            ['id' => $bogusId, 'status' => 'storage'],
        ]);

        $this->assertEquals(207, $response['status']);
        $this->assertEquals([$validId], $response['data']['updated']);
        $this->assertCount(1, $response['data']['errors']);
        $this->assertEquals($bogusId, $response['data']['errors'][0]['id']);
    }

    /**
     * Ownership check exercised on 'users' (owner_field = 'id') rather than 'items':
     * the 'items'/'categories' tables declare owner_field 'created_by' in permissions.php
     * but that column doesn't exist in their schema (dev/db.sql), so ownership is never
     * enforced there - a pre-existing gap also called out in SortingE2ETest::makeAdmin().
     * 'users' has a real, always-present owner column ('id'), so it's the one table where
     * update_own_only is actually enforced end-to-end.
     */
    public function testBulkUpdateDeniesRecordNotOwnedByUser()
    {
        $otherEmail = 'bulk-other-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $otherUserId = $this->registerUser($otherEmail, $this->password);
        $otherToken = $this->loginUser($otherEmail, $this->password);

        // A plain 'user' role (not admin) tries to bulk-update someone else's profile.
        $thirdEmail = 'bulk-third-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $this->registerUser($thirdEmail, $this->password);
        $thirdToken = $this->loginUser($thirdEmail, $this->password);

        $response = $this->makeRequest('PUT', '/users/bulk', [
            ['id' => $otherUserId, 'first_name' => 'Hacked'],
        ], [], $thirdToken);

        $this->assertEquals(400, $response['status']);
        $this->assertEmpty($response['data']['updated']);
        $this->assertCount(1, $response['data']['errors']);
        $this->assertEquals($otherUserId, $response['data']['errors'][0]['id']);
    }

    public function testBulkDeleteItemsSucceeds()
    {
        $created = $this->makeRequest('POST', '/items/bulk', [
            $this->itemPayload('delete-a'),
            $this->itemPayload('delete-b'),
        ]);
        [$idA, $idB] = $created['data']['created'];

        $response = $this->makeRequest('DELETE', '/items/bulk', ['ids' => [$idA, $idB]]);

        $this->assertEquals(200, $response['status']);
        $this->assertEqualsCanonicalizing([$idA, $idB], $response['data']['deleted']);
        $this->assertEmpty($response['data']['errors']);

        $getA = $this->makeRequest('GET', "/items/{$idA}");
        $this->assertEquals(404, $getA['status']);
    }

    public function testBulkDeleteRequiresIdsArray()
    {
        $response = $this->makeRequest('DELETE', '/items/bulk', ['notIds' => [1, 2]]);
        $this->assertEquals(400, $response['status']);
    }
}
