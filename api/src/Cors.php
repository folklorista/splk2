<?php
namespace App;

class Cors
{
    public static function setHeaders()
    {
        header("Access-Control-Allow-Origin: *"); // Pokud chceš povolit jen frontend, změň na konkrétní doménu
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Search-Query, X-Search-Columns, X-Sort-By, X-Sort-Direction, X-Pagination-Limit, X-Pagination-Offset");
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
