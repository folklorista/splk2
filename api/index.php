<?php
namespace App;

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Nastavení HTTP hlaviček
Cors::setHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
header(header: 'Content-Type: application/json');

// Načtení konfigurace
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$env    = $_ENV['APP_ENV'] ?? 'local';
$config = require __DIR__ . '/config/config.' . ($env === 'production' ? 'production' : 'local') . '.php';

// Inicializace
$logger    = new Logger($config['log']);
$db        = new Database(config: $config['database'], logger: $logger);
$auth      = new Auth(config: $config['auth'], db: $db, logger: $logger);
$endpoints = new Endpoints(db: $db, auth: $auth, logger: $logger);

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
