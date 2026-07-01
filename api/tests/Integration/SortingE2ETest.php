<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class SortingE2ETest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000/api/v1';
    private string $email = '';
    private string $password = 'TestPassword123!';
    private string $accessToken = '';
    private int $categoryId = 0;

    protected function setUp(): void
    {
        $this->email = 'sorting-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $userId = $this->registerUser();
        $this->makeAdmin($userId);
        $this->loginUser();
        $this->categoryId = $this->createCategory('SortTest Category ' . time() . '-' . rand(1000, 9999));
    }

    private function registerUser(): int
    {
        $response = $this->makeRequest('POST', '/register', [
            'email' => $this->email,
            'password' => $this->password,
            'first_name' => 'Sort',
            'last_name' => 'Test',
        ]);

        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    /**
     * Grant the admin role directly in the DB. Regular ('user') role read access
     * is filtered by an owner column ('created_by') that doesn't exist on the
     * 'items' table, so a plain registered user can't list items at all -
     * that's a separate pre-existing bug, unrelated to sorting. Using admin
     * (which reads everything, no owner filter) sidesteps it for this test.
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

        $defaultHeaders = ['Content-Type: application/json'];
        if (!empty($this->accessToken)) {
            $defaultHeaders[] = "Authorization: Bearer {$this->accessToken}";
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

    private function createItem(string $inventoryNumber, string $status = 'active'): int
    {
        $response = $this->makeRequest('POST', '/items', [
            'category_id' => $this->categoryId,
            'inventory_number' => $inventoryNumber,
            'status' => $status,
        ]);
        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    /**
     * Fetch items with a high limit so newly-created rows aren't cut off
     * by pagination defaults, then return the id order as returned by the API.
     */
    private function fetchItemIdOrder(string $sortParam): array
    {
        $response = $this->makeRequest('GET', "/items?sort={$sortParam}", null, [
            'X-Pagination-Limit: 1000',
        ]);

        $this->assertEquals(200, $response['status']);

        return array_column($response['data'], 'id');
    }

    /**
     * Multi-column sort: same status (tie), broken by -id (descending).
     * A single-column sort on status alone would leave the tie order undefined.
     */
    public function testMultiColumnSortBreaksTieOnSecondColumn()
    {
        $tieStatus = 'retired';

        $idFirst = $this->createItem('sorttest-tie-a-' . rand(1000, 9999), $tieStatus);
        $idSecond = $this->createItem('sorttest-tie-b-' . rand(1000, 9999), $tieStatus);

        $order = $this->fetchItemIdOrder('status,-id');

        $posFirst = array_search($idFirst, $order, true);
        $posSecond = array_search($idSecond, $order, true);

        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        // -id means higher id (created later) comes first
        $this->assertLessThan($posFirst, $posSecond);
    }

    public function testSortDirectionPrefixIsRespected()
    {
        $idFirst = $this->createItem('sorttest-dir-a-' . rand(1000, 9999));
        $idSecond = $this->createItem('sorttest-dir-b-' . rand(1000, 9999));

        $ascOrder = $this->fetchItemIdOrder('id');
        $descOrder = $this->fetchItemIdOrder('-id');

        $ascPosFirst = array_search($idFirst, $ascOrder, true);
        $ascPosSecond = array_search($idSecond, $ascOrder, true);
        $this->assertLessThan($ascPosSecond, $ascPosFirst);

        $descPosFirst = array_search($idFirst, $descOrder, true);
        $descPosSecond = array_search($idSecond, $descOrder, true);
        $this->assertLessThan($descPosFirst, $descPosSecond);
    }

    public function testInvalidSortColumnReturns400()
    {
        $maliciousSort = rawurlencode('id;DROP TABLE users;--');
        $response = $this->makeRequest('GET', "/items?sort={$maliciousSort}");

        $this->assertEquals(400, $response['status']);
    }

    public function testSortMetaReflectsRequestedColumns()
    {
        $this->createItem('sorttest-meta-' . rand(1000, 9999));

        $response = $this->makeRequest('GET', '/items?sort=inventory_number,-id', null, [
            'X-Pagination-Limit: 1000',
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertEquals(
            [
                ['column' => 'inventory_number', 'direction' => 'ASC'],
                ['column' => 'id', 'direction' => 'DESC'],
            ],
            $response['meta']['sorting']
        );
    }
}
