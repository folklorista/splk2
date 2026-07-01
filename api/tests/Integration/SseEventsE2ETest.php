<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class SseEventsE2ETest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000/api/v1';
    private string $email = '';
    private string $password = 'TestPassword123!';
    private string $accessToken = '';
    private int $userId = 0;
    private int $categoryId = 0;

    protected function setUp(): void
    {
        $this->email = 'sse-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $this->userId = $this->registerUser($this->email);
        $this->makeAdmin($this->userId);
        $this->accessToken = $this->loginUser($this->email);
        $this->categoryId = $this->createCategory('SseTest Category ' . time() . '-' . rand(1000, 9999));
    }

    private function registerUser(string $email): int
    {
        $response = $this->makeRequest('POST', '/register', [
            'email' => $email,
            'password' => $this->password,
            'first_name' => 'Sse',
            'last_name' => 'Test',
        ], [], '');

        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    /**
     * Same rationale as SortingE2ETest::makeAdmin - 'user' role read access on
     * 'items'/'categories' is filtered by an owner column ('created_by') that
     * doesn't exist in the DB schema, so a plain user can't see them at all.
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

    private function loginUser(string $email): string
    {
        $response = $this->makeRequest('POST', '/login', [
            'email' => $email,
            'password' => $this->password,
        ], [], '');

        $this->assertEquals(200, $response['status']);
        return $response['data']['accessToken'];
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

    /**
     * Opens a GET connection to /events and reads until the server closes it.
     * Passes maxDurationSec so the SERVER ends the stream on its own after a couple
     * seconds - relying on curl-side abandonment instead would leave the request
     * running server-side (the PHP built-in dev server doesn't detect an abandoned
     * connection reliably), which blocks the single-threaded dev server for every
     * later request in the suite. CURLOPT_TIMEOUT is only a safety net above that.
     */
    private function streamRequest(string $endpoint, ?string $bearerToken = null, int $serverDurationSec = 2): string
    {
        $separator = str_contains($endpoint, '?') ? '&' : '?';
        $url = $this->baseUrl . $endpoint . $separator . 'maxDurationSec=' . $serverDurationSec;
        $ch = curl_init($url);

        $headers = [];
        if ($bearerToken !== null) {
            $headers[] = "Authorization: Bearer {$bearerToken}";
        }

        $buffer = '';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $serverDurationSec + 5);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($handle, $chunk) use (&$buffer) {
            $buffer .= $chunk;
            return strlen($chunk);
        });
        curl_exec($ch);
        curl_close($ch);

        return $buffer;
    }

    private function createCategory(string $name, ?string $token = null): int
    {
        $response = $this->makeRequest('POST', '/categories', ['name' => $name], [], $token);
        $this->assertEquals(201, $response['status']);
        return (int) $response['data']['id'];
    }

    public function testEventsRequiresAuthentication()
    {
        // No Authorization header and no ?token= query param - should fail fast
        // with a normal JSON 401, never entering the streaming loop.
        $response = $this->makeRequest('GET', '/events', null, [], '');
        $this->assertEquals(401, $response['status']);
    }

    public function testEventsStreamsChangeViaBearerHeader()
    {
        $item = $this->makeRequest('POST', '/items', [
            'category_id' => $this->categoryId,
            'inventory_number' => 'sse-bearer-' . rand(1000, 9999),
            'status' => 'active',
        ]);
        $this->assertEquals(201, $item['status']);
        $itemId = $item['data']['id'];

        $buffer = $this->streamRequest('/events?lastEventId=0', $this->accessToken);

        $this->assertStringContainsString('event: change', $buffer);
        $this->assertStringContainsString('"table":"items"', $buffer);
        $this->assertStringContainsString('"recordId":' . $itemId, $buffer);
        $this->assertStringContainsString('"action":"DATA_INSERT"', $buffer);
    }

    public function testEventsStreamsChangeViaQueryToken()
    {
        $item = $this->makeRequest('POST', '/items', [
            'category_id' => $this->categoryId,
            'inventory_number' => 'sse-query-' . rand(1000, 9999),
            'status' => 'active',
        ]);
        $this->assertEquals(201, $item['status']);
        $itemId = $item['data']['id'];

        // No bearerToken passed here - auth happens purely via ?token=, the path
        // the browser's EventSource API relies on since it can't set headers.
        $buffer = $this->streamRequest('/events?lastEventId=0&token=' . $this->accessToken);

        $this->assertStringContainsString('event: change', $buffer);
        $this->assertStringContainsString('"recordId":' . $itemId, $buffer);
    }

    public function testEventsFiltersRecordsUserCannotRead()
    {
        // Plain (non-admin) user whose own profile gets changed by an admin.
        $otherEmail = 'sse-other-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $otherId = $this->registerUser($otherEmail);
        $otherToken = $this->loginUser($otherEmail);

        // A different plain (non-admin) bystander, who should NOT see that change.
        $watcherEmail = 'sse-watcher-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $this->registerUser($watcherEmail);
        $watcherToken = $this->loginUser($watcherEmail);

        // Admin updates the other user's profile - table 'users', owner_field 'id'.
        $update = $this->makeRequest('PUT', "/users/{$otherId}", ['first_name' => 'Hacked']);
        $this->assertEquals(200, $update['status']);

        $watcherBuffer = $this->streamRequest('/events?lastEventId=0', $watcherToken);
        $this->assertStringNotContainsString('"table":"users","recordId":' . $otherId, $watcherBuffer);

        // Positive control: the owner's own stream does see the change.
        $ownerBuffer = $this->streamRequest('/events?lastEventId=0', $otherToken);
        $this->assertStringContainsString('"table":"users","recordId":' . $otherId, $ownerBuffer);
        $this->assertStringContainsString('"action":"DATA_UPDATE"', $ownerBuffer);
    }
}
