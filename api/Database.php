<?php
// Database.php
class Database {
    private $pdo;

    public function __construct($config) {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // Získání všech záznamů z tabulky
    public function getAll($table) {
        $stmt = $this->pdo->query("SELECT * FROM {$table}");
        return $stmt->fetchAll();
    }

    // Získání jednoho záznamu podle ID
    public function getById($table, $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // Vložení nového záznamu
    public function insert($table, $data) {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    // Aktualizace záznamu
    public function update($table, $id, $data) {
        $fields = implode(", ", array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
        $data['id'] = $id;
        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$fields} WHERE id = :id");
        return $stmt->execute($data);
    }

    // Smazání záznamu
    public function delete($table, $id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
