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
$env = $_ENV['APP_ENV'] ?? 'local';
$config = require __DIR__ . '/config/config.' . ($env === 'production' ? 'production' : 'local') . '.php';

// Inicializace
$logger = new Logger($config['log']);
$db = new Database(config: $config['database'], logger: $logger);
$auth = new Auth(config: $config['auth'], db: $db, logger: $logger);
$endpoints = new Endpoints(db: $db, auth: $auth, logger: $logger);

// Získání HTTP metody a endpointu
$method = $_SERVER['REQUEST_METHOD'];
$path = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

// Validace endpointu
$pathIndex = $config['pathIndex'];
if (count($path) == 0 || empty($path[$pathIndex['table']])) {
    Response::send(400, "Routing failed", null, "Invalid endpoint");
}

// Rozdělení endpointu na tabulku a ID
$tableName = $path[$pathIndex['table']];
$id = $path[$pathIndex['id']] ?? null;

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
if (!$user) {
    Response::send(401, "Unauthorized access", null, "You must be logged in to access this resource");
}

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
        } elseif ($id) {
            Response::sendPrepared($endpoints->getRecordByIdEndpoint($tableName, $id));
        } else {
            Response::sendPrepared($endpoints->getAllRecords($tableName));
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            Response::sendPrepared($endpoints->createRecordEndpoint($tableName, $data));
        } else {
            Response::send(400, "Empty input");
        }
        break;

    case 'PUT':
        if ($id) {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                Response::sendPrepared($endpoints->updateRecordEndpoint($tableName, $id, $data));
            } else {
                Response::send(400, "Empty input");
            }
        } else {
            Response::send(400, "ID is required");
        }
        break;

    case 'DELETE':
        if ($id) {
            Response::sendPrepared($endpoints->deleteRecordEndpoint($tableName, $id));
        } else {
            Response::send(400, "ID is required");
        }
        break;

    default:
        Response::send(405, "Method not allowed");
        break;
}
