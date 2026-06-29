<?php
namespace App;

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Nastavení HTTP hlaviček
Cors::setHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
header(header: 'Content-Type: application/json');

// Načtení konfigurace
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$env    = $_ENV['APP_ENV'] ?? 'local';
$config = require __DIR__ . '/../config/config.' . ($env === 'production' ? 'production' : 'local') . '.php';

// Inicializace
$logger    = new Logger($config['log']);
$db        = new Database(config: $config['database'], logger: $logger);
$auth      = new Auth(config: $config['auth'], db: $db, logger: $logger);

// Load table rules
$tableRules = require __DIR__ . '/../config/table-rules.php';
$validator  = new RuleValidator(rules: $tableRules, db: $db, logger: $logger);

$endpoints = new Endpoints(db: $db, auth: $auth, logger: $logger, validator: $validator);

// Initialize rate limiter
$rateLimiter = new RateLimiter(
    logger: $logger,
    storePath: __DIR__ . '/../../log/rate_limit',
    maxRequests: 100,
    windowSeconds: 60
);

// Apply rate limiting (excluding docs/health/login/register)
$clientIp = RateLimiter::getClientIdentifier();
$rateLimitExempt = ['login', 'register', 'health', 'docs', 'openapi.yaml'];

// Perform rate limit check for non-exempt endpoints
if (!isset($_GET['skipRateLimit']) && !in_array($tableName ?? '', $rateLimitExempt)) {
    $limitCheck = $rateLimiter->checkLimit($clientIp);

    if (!$limitCheck['allowed']) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . max(1, $limitCheck['reset_at'] - time()));
        echo json_encode([
            'status' => 429,
            'message' => 'Too Many Requests',
            'data' => null,
            'error' => 'Rate limit exceeded. Max ' . $limitCheck['limit'] . ' requests per ' . 60 . ' seconds.',
            'meta' => [
                'limit' => $limitCheck['limit'],
                'remaining' => $limitCheck['remaining'],
                'reset_at' => $limitCheck['reset_at'],
            ],
        ]);
        exit;
    }
}

// Add rate limit headers to responses
header('X-RateLimit-Limit: 100');
header('X-RateLimit-Remaining: ' . max(0, $limitCheck['remaining'] ?? 100));
header('X-RateLimit-Reset: ' . ($limitCheck['reset_at'] ?? (time() + 60)));

// Získání HTTP metody a endpointu
$method = $_SERVER['REQUEST_METHOD'];

// Rozparsování URL
$parsedUrl = parse_url($_SERVER['REQUEST_URI']);

// Cesta
$path = explode('/', trim($parsedUrl['path'], '/'));

// Query string (pokud existuje)
$queryParams = [];
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParams);
}

// Validace endpointu
$pathIndex = $config['pathIndex'];
if (count($path) == 0 || empty($path[$pathIndex['table']])) {
    Response::send(400, "Routing failed", null, "Invalid endpoint");
}

// Rozdělení endpointu na tabulku a ID
$tableName = $path[$pathIndex['table']];
$id        = $path[$pathIndex['id']] ?? null;

// Health check endpoint (no authentication required)
if ($tableName === 'health' && $method === 'GET') {
    $dbStatus = 'OK';
    try {
        $db->execute('SELECT 1');
    } catch (\Exception $e) {
        $dbStatus = 'ERROR: ' . $e->getMessage();
    }

    $uptime = function_exists('uptime') ? uptime() : (
        isset($_SERVER['REQUEST_TIME']) ? time() - $_SERVER['REQUEST_TIME'] : 0
    );

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $dbStatus === 'OK' ? 'operational' : 'degraded',
        'database' => $dbStatus,
        'uptime' => $uptime,
        'timestamp' => date('c'),
        'version' => '1.0.0',
    ]);
    exit;
}

// API Documentation UI (no authentication required)
if ($tableName === 'docs' && $method === 'GET') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>SPLK2 API Documentation</title>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: 'Roboto', sans-serif;
            }
        </style>
    </head>
    <body>
        <redoc spec-url='/openapi.yaml'></redoc>
        <script src="https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// OpenAPI schema endpoint (no authentication required)
if ($tableName === 'openapi.yaml' && $method === 'GET') {
    $openapiPath = __DIR__ . '/openapi.yaml';
    if (file_exists($openapiPath)) {
        http_response_code(200);
        header('Content-Type: application/x-yaml; charset=utf-8');
        echo file_get_contents($openapiPath);
        exit;
    }
}

// Logika pro login a registraci
if ($tableName == 'login' && $method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    Response::sendPrepared($endpoints->loginUser($data));
} elseif ($tableName == 'register' && $method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    Response::sendPrepared($endpoints->registerUser($data));
}

// Ověření tokenu pro ostatní endpointy
$user = $auth->authenticate();
if (! $user) {
    Response::send(401, "Unauthorized access", null, "You must be logged in to access this resource");
}

// Endpoint pro záznamy na základě cizích klíčů
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['foreignKeys'])) {
    $table = $_GET['table'] ?? null;
    if (! $table) {
        Response::send(400, 'Invalid request', null, 'Table name is required.');
        return;
    }

    $queryParams = $_GET;
    unset($queryParams['table'], $queryParams['foreignKeys']);

    $endpoints->handleForeignKeys($table, $queryParams);
    return;
}

// Zpracování stránkování a řazení z HTTP hlaviček
$limit         = isset($_SERVER['HTTP_X_PAGINATION_LIMIT']) ? (int) $_SERVER['HTTP_X_PAGINATION_LIMIT'] : null;
$offset        = isset($_SERVER['HTTP_X_PAGINATION_OFFSET']) ? (int) $_SERVER['HTTP_X_PAGINATION_OFFSET'] : null;
$orderBy       = $_SERVER['HTTP_X_SORT_BY'] ?? null;
$orderDir      = strtoupper($_SERVER['HTTP_X_SORT_DIRECTION'] ?? 'ASC');
$searchQuery   = $_SERVER['HTTP_X_SEARCH_QUERY'] ?? null;
$searchColumns = isset($_SERVER['HTTP_X_SEARCH_COLUMNS']) ? explode(',', $_SERVER['HTTP_X_SEARCH_COLUMNS']) : null;

// Ověření, zda orderDir obsahuje jen "ASC" nebo "DESC"
$orderDir = in_array($orderDir, ['ASC', 'DESC']) ? $orderDir : 'ASC';

// CRUD operace (jen pro přihlášené uživatele)
switch ($method) {
    case 'GET':
        if ($tableName === 'schema' && isset($id)) {
            $schema = $db->getSchema($id);
            if ($schema) {
                echo json_encode($schema);
            } else {
                Response::send(404, "Table not found");
            }
        } elseif ($tableName === 'audit_logs' && !isset($id)) {
            // Special handling for audit_logs with filtering by table_name and record_id
            $whereClause = '';
            $filterTable = $_GET['table_name'] ?? null;
            $filterId = $_GET['record_id'] ?? null;

            if ($filterTable || $filterId) {
                $conditions = [];
                if ($filterTable && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $filterTable)) {
                    $conditions[] = "`table_name` = '" . addslashes($filterTable) . "'";
                }
                if ($filterId && is_numeric($filterId)) {
                    $conditions[] = "`record_id` = " . intval($filterId);
                }
                $whereClause = implode(' AND ', $conditions);
            }

            Response::sendPrepared(
                $endpoints->getAllRecords($tableName, $whereClause, $limit, $offset, $orderBy, $orderDir, $searchQuery, $searchColumns)
            );
        } elseif (in_array($tableName, ['categories', 'groups']) && ! isset($id)) {
            Response::sendPrepared($endpoints->loadTreeEndpoint($tableName));

        } elseif ($id === 'search' && isset($_GET['search'])) {
            // Přidání endpointu pro vyhledávání
            $searchQuery   = $_GET['search'];
            $searchResults = $db->searchRecords($tableName, $searchQuery);
            if ($searchResults) {
                echo json_encode($searchResults);
            } else {
                Response::send(404, "No records found matching search criteria");
            }
        } elseif ($id === 'options') {
            // Přidání endpointu pro možnosti cizího klíče
            $options = $db->getForeignKeyOptions($tableName);
            if ($options) {
                echo json_encode($options);
            } else {
                Response::send(404, "No options found for referenced table");
            }
        } elseif ($id) {
            Response::sendPrepared($endpoints->getRecordByIdEndpoint($tableName, $id));
        } else {
            Response::sendPrepared(
                $endpoints->getAllRecords($tableName, "", $limit, $offset, $orderBy, $orderDir, $searchQuery, $searchColumns)
            );
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            Response::sendPrepared($endpoints->createRecordEndpoint($tableName, $data, $user['user']));
        } else {
            Response::send(400, "Empty input");
        }
        break;

    case 'PUT':
        if ($id) {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                Response::sendPrepared($endpoints->updateRecordEndpoint($tableName, $id, $data, $user['user']));
            } else {
                Response::send(400, "Empty input");
            }
        } else {
            if (in_array($tableName, ['categories', 'groups'])) {
                $data = json_decode(file_get_contents('php://input'), true);
                if ($data) {
                    Response::sendPrepared($endpoints->treeSaveOrUpdateEndpoint($tableName, $data, $user['user']));
                } else {
                    Response::send(400, "Empty input");
                }
            } else {
                Response::send(400, "ID is required");
            }
        }
        break;

    case 'DELETE':
        if ($id) {
            Response::sendPrepared($endpoints->deleteRecordEndpoint($tableName, $id, $user['user']));
        } else {
            Response::send(400, "ID is required");
        }
        break;

    default:
        Response::send(405, "Method not allowed");
        break;
}
