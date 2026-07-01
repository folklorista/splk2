<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class EnhancedSearchE2ETest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000/api/v1';
    private string $email = '';
    private string $password = 'TestPassword123!';
    private string $accessToken = '';
    private int $categoryId = 0;

    protected function setUp(): void
    {
        $this->email = 'search-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $userId = $this->registerUser();
        $this->makeAdmin($userId);
        $this->loginUser();
        $this->categoryId = $this->createCategory('SearchTest Category ' . time() . '-' . rand(1000, 9999));
    }

    private function registerUser(): int
    {
        $response = $this->makeRequest('POST', '/register', [
            'email' => $this->email,
            'password' => $this->password,
            'first_name' => 'Search',
            'last_name' => 'Test',
        ]);

        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    // See SortingE2ETest for why admin is used: 'items' has no owner column for
    // the regular 'user' role's read_own_only filter to work against.
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

    private function searchItems(string $query): array
    {
        return $this->makeRequest('GET', '/items?' . http_build_query(['filter' => ['category_id' => $this->categoryId]]), null, [
            'X-Search-Query: ' . $query,
            'X-Search-Columns: inventory_number',
            'X-Pagination-Limit: 1000',
        ]);
    }

    public function testFilterByExactColumnValue()
    {
        $matchId = $this->createItem('filtertest-match-' . rand(1000, 9999), 'retired');
        $this->createItem('filtertest-other-' . rand(1000, 9999), 'active');

        $response = $this->makeRequest('GET', "/items?filter[category_id]={$this->categoryId}&filter[status]=retired", null, [
            'X-Pagination-Limit: 1000',
        ]);

        $this->assertEquals(200, $response['status']);
        $ids = array_column($response['data'], 'id');
        $this->assertContains($matchId, $ids);
        $this->assertCount(1, $ids);
    }

    public function testFilterWithComparisonOperator()
    {
        $this->createItem('filtertest-cmp-a-' . rand(1000, 9999));
        $secondId = $this->createItem('filtertest-cmp-b-' . rand(1000, 9999));

        $response = $this->makeRequest('GET', "/items?filter[category_id]={$this->categoryId}&filter[id][gte]={$secondId}", null, [
            'X-Pagination-Limit: 1000',
        ]);

        $this->assertEquals(200, $response['status']);
        $ids = array_column($response['data'], 'id');
        foreach ($ids as $id) {
            $this->assertGreaterThanOrEqual($secondId, $id);
        }
        $this->assertContains($secondId, $ids);
    }

    public function testFilterRejectsUnknownColumn()
    {
        $response = $this->makeRequest('GET', '/items?filter[nonexistent_column]=x');
        $this->assertEquals(400, $response['status']);
    }

    public function testBooleanSearchAndRequiresBothTerms()
    {
        $suffix = rand(100000, 999999);
        $bothId = $this->createItem("zzzalpha{$suffix} zzzbeta{$suffix}");
        $onlyAlphaId = $this->createItem("zzzalpha{$suffix} zzzgamma{$suffix}");

        $response = $this->searchItems("zzzalpha{$suffix} AND zzzbeta{$suffix}");

        $this->assertEquals(200, $response['status']);
        $ids = array_column($response['data'], 'id');
        $this->assertContains($bothId, $ids);
        $this->assertNotContains($onlyAlphaId, $ids);
    }

    public function testBooleanSearchNotExcludesTerm()
    {
        $suffix = rand(100000, 999999);
        $withBetaId = $this->createItem("zzzalpha{$suffix} zzzbeta{$suffix}");
        $withGammaId = $this->createItem("zzzalpha{$suffix} zzzgamma{$suffix}");

        $response = $this->searchItems("zzzalpha{$suffix} NOT zzzbeta{$suffix}");

        $this->assertEquals(200, $response['status']);
        $ids = array_column($response['data'], 'id');
        $this->assertContains($withGammaId, $ids);
        $this->assertNotContains($withBetaId, $ids);
    }

    public function testFuzzySearchFallbackMatchesTypo()
    {
        $suffix = rand(10000, 99999);
        $correct = "gadgetword{$suffix}";
        // Two substituted characters ("word" -> "wrod"): edit distance 2, right at the fallback threshold.
        $typo = "gadgetwrod{$suffix}";

        $itemId = $this->createItem($correct);

        $response = $this->searchItems($typo);

        $this->assertEquals(200, $response['status']);
        $ids = array_column($response['data'], 'id');
        $this->assertContains($itemId, $ids);
        $this->assertTrue($response['meta']['search']['fuzzy_fallback']);
    }

    public function testFacetedSearchReturnsCounts()
    {
        $suffix = rand(100000, 999999);
        $this->createItem("facettest-a1-{$suffix}", "repair");
        $this->createItem("facettest-a2-{$suffix}", "repair");
        $this->createItem("facettest-b1-{$suffix}", "storage");

        $response = $this->makeRequest(
            'GET',
            "/items?filter[category_id]={$this->categoryId}&facets=status",
            null,
            ['X-Pagination-Limit: 1000']
        );

        $this->assertEquals(200, $response['status']);
        $this->assertEquals(2, $response['meta']['facets']['status']['repair']);
        $this->assertEquals(1, $response['meta']['facets']['status']['storage']);
    }
}
