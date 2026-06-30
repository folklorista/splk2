<?php
namespace App;

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables BEFORE setting headers (CORS needs env config)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$env    = $_ENV['APP_ENV'] ?? 'local';
$config = require __DIR__ . '/../config/config.' . ($env === 'production' ? 'production' : 'local') . '.php';

// Now set CORS headers (after env is loaded)
try {
    Cors::setHeaders();
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 500,
        'message' => 'Server configuration error',
        'error' => $e->getMessage(),
    ]);
    exit(1);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
header('Content-Type: application/json');

// Initialize Request ID for correlation/tracing
$requestIdManager = new RequestIdManager();
$requestId = $requestIdManager->getId();
$requestIdManager->setResponseHeader();

// Inicializace
$logger    = new Logger($config['log']);
Logger::setRequestId($requestId);  // Set request ID in logger for all log entries
$db        = new Database(config: $config['database'], logger: $logger);
$auth      = new Auth(config: $config['auth'], db: $db, logger: $logger);
$passwordReset = new PasswordReset(db: $db, logger: $logger, auth: $auth);

// Load table rules and permissions
$tableRules = require __DIR__ . '/../config/table-rules.php';
$permissions = require __DIR__ . '/../config/permissions.php';
$validator  = new RuleValidator(rules: $tableRules, db: $db, logger: $logger);
$rbac       = new RoleBasedAccessControl(db: $db, logger: $logger);
$permissionChecker = new PermissionChecker(permissions: $permissions, rbac: $rbac, logger: $logger);

$endpoints = new Endpoints(db: $db, auth: $auth, logger: $logger, validator: $validator, rbac: $rbac);

// Initialize rate limiter
// Default guest limit is 10 requests per minute, can be overridden by env variable
$guestLimit = (int)($_ENV['RATE_LIMIT_GUEST'] ?? 10);
$rateLimiter = new RateLimiter(
    logger: $logger,
    storePath: __DIR__ . '/../../log/rate_limit',
    maxRequests: $guestLimit,
    windowSeconds: 60
);

// Endpoints exempt from rate limiting
$rateLimitExempt = ['health', 'docs', 'openapi.yaml'];

// Parse API request with versioning support
try {
    $routing = ApiRouter::parseRequest($_SERVER['REQUEST_URI']);
} catch (\Exception $e) {
    Response::send(400, "Routing failed", null, $e->getMessage());
}

$version = $routing['version'];
$method = $routing['method'];
$tableName = $routing['resource'];
$id = $routing['id'];
$path = $routing['path'];
$pathIndex = $routing['pathIndex'];

// Query string (pokud existuje)
$queryParams = [];
$parsedUrl = parse_url($_SERVER['REQUEST_URI']);
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParams);
}

// Validace endpointu
if (count($path) == 0 || empty($path[$pathIndex['table']])) {
    Response::send(400, "Routing failed", null, "Invalid endpoint");
}

// Add API version info to response headers
header("X-API-Version: $version");

// Apply rate limiting to non-exempt endpoints
$clientIp = RateLimiter::getClientIdentifier();
$userRole = 'guest';
$limitIdentifier = $clientIp;

// Check if user is authenticated for better rate limit
$authHeader = null;
if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
}
if (!$authHeader && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}

// For authenticated requests, use user ID instead of IP for better tracking
if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
    $token = str_replace('Bearer ', '', $authHeader);
    try {
        $decodedToken = $auth->verifyToken($token);
        if ($decodedToken && isset($decodedToken['sub'])) {
            $limitIdentifier = 'user_' . $decodedToken['sub'];
            // Try to get user role for role-based limiting
            $userResult = $db->get('users', $decodedToken['sub']);
            if ($userResult && $userResult['status'] === 200) {
                $rbac = new RoleBasedAccessControl($db, $logger);
                if ($rbac->hasRole((object)$userResult['data'], 'admin')) {
                    $userRole = 'admin';
                } else {
                    $userRole = 'user';
                }
            }
        }
    } catch (\Exception $e) {
        // Not a valid token, use IP-based limiting
    }
}

// Perform rate limit check for non-exempt endpoints
if (!in_array($tableName, $rateLimitExempt)) {
    $limitCheck = $rateLimiter->checkLimit($limitIdentifier, $userRole);

    if (!$limitCheck['allowed']) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . max(1, $limitCheck['reset_at'] - time()));
        header('X-RateLimit-Limit: ' . $limitCheck['limit']);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . $limitCheck['reset_at']);
        echo json_encode([
            'status' => 429,
            'message' => 'Too Many Requests',
            'data' => null,
            'error' => 'Rate limit exceeded. Max ' . $limitCheck['limit'] . ' requests per minute.',
            'meta' => [
                'limit' => $limitCheck['limit'],
                'remaining' => 0,
                'reset_at' => $limitCheck['reset_at'],
                'retry_after' => max(1, $limitCheck['reset_at'] - time()),
            ],
        ]);
        exit;
    }
} else {
    $limitCheck = ['limit' => 0, 'remaining' => 0, 'reset_at' => time() + 60];
}

// Add rate limit headers to all responses
header('X-RateLimit-Limit: ' . $limitCheck['limit']);
header('X-RateLimit-Remaining: ' . max(0, $limitCheck['remaining'] ?? 0));
header('X-RateLimit-Reset: ' . ($limitCheck['reset_at'] ?? (time() + 60)));

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

// API Versions endpoint (no authentication required)
if ($tableName === 'versions' && $method === 'GET') {
    $supportedVersions = ApiRouter::getSupportedVersions();
    $versionsInfo = [];

    foreach ($supportedVersions as $v) {
        $deprecationInfo = ApiRouter::getDeprecationInfo($v);
        $versionsInfo[] = [
            'version' => $v,
            'status' => $deprecationInfo ? 'deprecated' : 'active',
            'deprecation' => $deprecationInfo,
        ];
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'default_version' => 'v1',
            'supported_versions' => $versionsInfo,
            'documentation_url' => '/docs',
        ],
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
} elseif ($tableName == 'auth' && isset($path[$pathIndex['table'] + 1]) && $path[$pathIndex['table'] + 1] == 'refresh' && $method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $refreshToken = $data['refreshToken'] ?? null;

    if (!$refreshToken) {
        Response::send(400, "Refresh token is required");
        exit;
    }

    $result = $auth->refreshAccessToken($refreshToken);
    if (!$result) {
        Response::send(401, "Invalid or expired refresh token");
        exit;
    }

    Response::send(200, "Token refreshed", $result);
    exit;
} elseif ($tableName == 'auth' && isset($path[$pathIndex['table'] + 1]) && $path[$pathIndex['table'] + 1] == 'password-reset' && $method == 'POST') {
    // Request password reset: POST /auth/password-reset
    // Body: { email: "user@example.com" }
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? null;

    if (!$email) {
        Response::send(400, "Email is required");
        exit;
    }

    $result = $passwordReset->requestReset($email);
    Response::sendPrepared(Response::prepare($result['status'], $result['message'], $result['data']));
    exit;
} elseif ($tableName == 'auth' && isset($path[$pathIndex['table'] + 1]) && $path[$pathIndex['table'] + 1] == 'password-reset' && isset($path[$pathIndex['table'] + 2]) && $method == 'POST') {
    // Complete password reset: POST /auth/password-reset/{token}
    // Body: { password: "newPassword123" }
    $token = $path[$pathIndex['table'] + 2] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    $newPassword = $data['password'] ?? null;

    if (!$token || !$newPassword) {
        Response::send(400, "Token and password are required");
        exit;
    }

    $result = $passwordReset->completeReset($token, $newPassword);
    Response::sendPrepared(Response::prepare($result['status'], $result['message'], $result['data']));
    exit;
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

// Role management routes (users/{id}/roles/{roleId})
if ($tableName === 'users' && $id && isset($path[$pathIndex['id'] + 1]) && $path[$pathIndex['id'] + 1] === 'roles') {
    $roleId = $path[$pathIndex['id'] + 2] ?? null;

    switch ($method) {
        case 'GET':
            // GET /users/{id}/roles - get all roles for user
            Response::sendPrepared($endpoints->getUserRolesEndpoint($id));
            exit;

        case 'POST':
            // POST /users/{id}/roles/{roleId} - assign role to user
            if (!$roleId) {
                $data = json_decode(file_get_contents('php://input'), true);
                $roleId = $data['role_id'] ?? null;
            }
            if (!$roleId) {
                Response::send(400, "Role ID is required");
                exit;
            }
            Response::sendPrepared($endpoints->assignRoleToUserEndpoint($id, $roleId, $user['user']));
            exit;

        case 'DELETE':
            // DELETE /users/{id}/roles/{roleId} - remove role from user
            if (!$roleId) {
                Response::send(400, "Role ID is required");
                exit;
            }
            Response::sendPrepared($endpoints->removeRoleFromUserEndpoint($id, $roleId, $user['user']));
            exit;
    }
}

// Webhook management routes (webhooks, webhooks/{id}, webhooks/{id}/test)
if ($tableName === 'webhooks') {
    switch ($method) {
        case 'GET':
            if ($id === 'test') {
                Response::send(400, "Webhook ID is required for test");
                exit;
            }
            if ($id) {
                // GET /webhooks/{id} - get single webhook
                Response::sendPrepared($endpoints->getWebhookEndpoint($id, $user['user']));
            } else {
                // GET /webhooks - get all webhooks
                Response::sendPrepared($endpoints->getWebhooksEndpoint($user['user']));
            }
            exit;

        case 'POST':
            if ($id && isset($path[$pathIndex['id'] + 1]) && $path[$pathIndex['id'] + 1] === 'test') {
                // POST /webhooks/{id}/test - test webhook
                Response::sendPrepared($endpoints->testWebhookEndpoint($id, $user['user']));
            } else {
                // POST /webhooks - create new webhook
                $data = json_decode(file_get_contents('php://input'), true);
                Response::sendPrepared($endpoints->createWebhookEndpoint($data, $user['user']));
            }
            exit;

        case 'PUT':
            if ($id) {
                // PUT /webhooks/{id} - update webhook
                $data = json_decode(file_get_contents('php://input'), true);
                Response::sendPrepared($endpoints->updateWebhookEndpoint($id, $data, $user['user']));
            } else {
                Response::send(400, "Webhook ID is required");
            }
            exit;

        case 'DELETE':
            if ($id) {
                // DELETE /webhooks/{id} - delete webhook
                Response::sendPrepared($endpoints->deleteWebhookEndpoint($id, $user['user']));
            } else {
                Response::send(400, "Webhook ID is required");
            }
            exit;
    }
}

// File management routes (files/upload, files/{id}/download, files/{id}, files/my)
if ($tableName === 'files') {
    switch ($method) {
        case 'GET':
            if ($id === 'my') {
                // GET /files/my - get current user's files
                Response::sendPrepared($endpoints->getUserFilesEndpoint($user['user']));
            } elseif (isset($path[$pathIndex['id'] + 1]) && $path[$pathIndex['id'] + 1] === 'download') {
                // GET /files/{id}/download - download file
                $endpoints->downloadFileEndpoint($id, $user['user']);
            } elseif ($id) {
                // GET /files/{id} - get file details
                Response::sendPrepared($endpoints->getFileEndpoint($id, $user['user']));
            } else {
                // GET /files - list all files (admin only)
                Response::sendPrepared($endpoints->getAllRecords('files', '', null, null, null, 'ASC', null, null));
            }
            exit;

        case 'POST':
            if ($id === 'upload' || isset($_FILES['file'])) {
                // POST /files/upload - upload file
                Response::sendPrepared($endpoints->uploadFileEndpoint($_FILES['file'] ?? [], $user['user']));
            } else {
                Response::send(400, "No file provided");
            }
            exit;

        case 'DELETE':
            if ($id) {
                // DELETE /files/{id} - delete file
                Response::sendPrepared($endpoints->deleteFileEndpoint($id, $user['user']));
            } else {
                Response::send(400, "File ID is required");
            }
            exit;
    }
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
            // Special handling for audit_logs with filtering by table_name, record_id, and action_id
            // Use WhereClauseBuilder for safe parameterized queries
            $builder = new WhereClauseBuilder();

            $filterTable = $queryParams['table_name'] ?? null;
            $filterId = $queryParams['record_id'] ?? null;
            $filterActionId = $queryParams['action_id'] ?? null;

            if ($filterTable && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $filterTable)) {
                $builder->eq('table_name', $filterTable);
            }
            if ($filterId && is_numeric($filterId)) {
                $builder->eq('record_id', intval($filterId));
            }
            if ($filterActionId && is_numeric($filterActionId)) {
                $builder->eq('action_id', intval($filterActionId));
            }

            $whereClause = $builder->build();
            $whereParams = $builder->getParams();

            Response::sendPrepared(
                $endpoints->getAllRecordsWithParams($tableName, $whereClause, $whereParams, $limit, $offset, $orderBy, $orderDir, $searchQuery, $searchColumns)
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
            // First check if record exists
            $record = $db->get($tableName, $id);
            if (!$record || $record['status'] !== 200) {
                Response::send(404, "Record not found");
            } else {
                // Check read permission for single record
                $permCheck = $permissionChecker->canAccess($tableName, 'read', $user['user'], (int)$id);
                if (!$permCheck['allowed']) {
                    Response::send(403, "Forbidden", null, $permCheck['reason']);
                } else {
                    // Need to check if user owns the record if read_own_only
                    $ownerField = $permissionChecker->getOwnerField($tableName);
                    $recordOwnerId = $record['data'][$ownerField] ?? null;
                    $ownerCheck = $permissionChecker->canAccess($tableName, 'read', $user['user'], $recordOwnerId);
                    if (!$ownerCheck['allowed']) {
                        Response::send(403, "Forbidden", null, $ownerCheck['reason']);
                    } else {
                        Response::sendPrepared($endpoints->getRecordByIdEndpoint($tableName, $id));
                    }
                }
            }
        } else {
            // Check read permission for list
            $permCheck = $permissionChecker->canAccess($tableName, 'read', $user['user']);
            if (!$permCheck['allowed']) {
                Response::send(403, "Forbidden", null, $permCheck['reason']);
            } else {
                // Apply ownership filter if needed
                $ownerFilter = $permissionChecker->getFilterForReadAccess($tableName, $user['user']);
                $whereClause = $ownerFilter ? ($whereClause ? "$whereClause AND $ownerFilter" : $ownerFilter) : $whereClause;
                Response::sendPrepared(
                    $endpoints->getAllRecords($tableName, $whereClause, $limit, $offset, $orderBy, $orderDir, $searchQuery, $searchColumns)
                );
            }
        }
        break;

    case 'POST':
        // Check create permission
        $permCheck = $permissionChecker->canAccess($tableName, 'create', $user['user']);
        if (!$permCheck['allowed']) {
            Response::send(403, "Forbidden", null, $permCheck['reason']);
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                Response::sendPrepared($endpoints->createRecordEndpoint($tableName, $data, $user['user']));
            } else {
                Response::send(400, "Empty input");
            }
        }
        break;

    case 'PUT':
        if ($id) {
            // Check update permission
            $record = $db->get($tableName, $id);
            if (!$record || $record['status'] !== 200) {
                Response::send(404, "Record not found");
            } else {
                $ownerField = $permissionChecker->getOwnerField($tableName);
                $recordOwnerId = $record['data'][$ownerField] ?? null;
                $permCheck = $permissionChecker->canAccess($tableName, 'update', $user['user'], $recordOwnerId);
                if (!$permCheck['allowed']) {
                    Response::send(403, "Forbidden", null, $permCheck['reason']);
                } else {
                    $data = json_decode(file_get_contents('php://input'), true);
                    if ($data) {
                        Response::sendPrepared($endpoints->updateRecordEndpoint($tableName, $id, $data, $user['user']));
                    } else {
                        Response::send(400, "Empty input");
                    }
                }
            }
        } else {
            if (in_array($tableName, ['categories', 'groups'])) {
                $permCheck = $permissionChecker->canAccess($tableName, 'update', $user['user']);
                if (!$permCheck['allowed']) {
                    Response::send(403, "Forbidden", null, $permCheck['reason']);
                } else {
                    $data = json_decode(file_get_contents('php://input'), true);
                    if ($data) {
                        Response::sendPrepared($endpoints->treeSaveOrUpdateEndpoint($tableName, $data, $user['user']));
                    } else {
                        Response::send(400, "Empty input");
                    }
                }
            } else {
                Response::send(400, "ID is required");
            }
        }
        break;

    case 'DELETE':
        if ($id) {
            // Check delete permission
            $record = $db->get($tableName, $id);
            if (!$record || $record['status'] !== 200) {
                Response::send(404, "Record not found");
            } else {
                $ownerField = $permissionChecker->getOwnerField($tableName);
                $recordOwnerId = $record['data'][$ownerField] ?? null;
                $permCheck = $permissionChecker->canAccess($tableName, 'delete', $user['user'], $recordOwnerId);
                if (!$permCheck['allowed']) {
                    Response::send(403, "Forbidden", null, $permCheck['reason']);
                } else {
                    Response::sendPrepared($endpoints->deleteRecordEndpoint($tableName, $id, $user['user']));
                }
            }
        } else {
            Response::send(400, "ID is required");
        }
        break;

    default:
        Response::send(405, "Method not allowed");
        break;
}
