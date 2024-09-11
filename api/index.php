<?php
// index.php

header('Content-Type: application/json');
require 'config.php';
require 'Database.php';

// Načtení konfigurace a inicializace DB
$config = require 'config.php';
$db = new Database($config);

// Získání HTTP metody a endpointu (např. /api/users/1)
$method = $_SERVER['REQUEST_METHOD'];
$path = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

if (count($path) == 0 || empty($path[0])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid endpoint"]);
    exit;
}

$table = $path[0];
$id = $path[1] ?? null;

// Zpracování požadavků podle HTTP metody
switch ($method) {
    case 'GET':
        if ($id) {
            $result = $db->getById($table, $id);
            if ($result) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Record not found"]);
            }
        } else {
            $result = $db->getAll($table);
            echo json_encode($result);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            $insertId = $db->insert($table, $data);
            echo json_encode(['id' => $insertId]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input"]);
        }
        break;

    case 'PUT':
        if ($id) {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                $success = $db->update($table, $id, $data);
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Invalid input"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID is required"]);
        }
        break;

    case 'DELETE':
        if ($id) {
            $success = $db->delete($table, $id);
            echo json_encode(['success' => $success]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID is required"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
