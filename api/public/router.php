<?php
$requestedResource = $_SERVER['REQUEST_URI'];
$uriPath = parse_url($requestedResource, PHP_URL_PATH);

// Remove leading slash
$uriPath = ltrim($uriPath, '/');

// Check if file exists in public directory
if ($uriPath !== '' && file_exists(__DIR__ . '/' . $uriPath) && is_file(__DIR__ . '/' . $uriPath)) {
    return false;
}

// Route all requests to parent index.php
require_once __DIR__ . '/../index.php';
