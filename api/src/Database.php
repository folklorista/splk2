<?php
namespace App;

use PDO;
use PDOException;

class Database
{
    private Logger $logger;
    private PDO $pdo;

    public function __construct($config, Logger $logger)
    {
        $this->logger = $logger;

        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // Získání všech záznamů z tabulky
    public function getAll(string $table)
    {
        try {
            $stmt = $this->pdo->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns = array_filter($columns, function ($column) {
                return $column['Field'] !== 'password';
            });

            $columnNames = array_map(function ($column) {
                return $column['Field'];
            }, $columns);

            $columnsList = implode(', ', $columnNames);
            $stmt = $this->pdo->query("SELECT {$columnsList} FROM `{$table}`");

            $result = $stmt->fetchAll();

            if (!$result) {
                return Response::prepare(404, "No records found");
            } else {
                return Response::prepare(200, "Records found", $result);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return Response::prepare(404, "Table not found");
            } else {
                return Response::prepare(400, "Records not found", null, $e->getMessage());
            }
        }
    }

    // Získání jednoho záznamu podle ID
    public function get(string $table, string | int $id, string $key = 'id')
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE {$key} = :id");
            $stmt->execute(['id' => $id]); // line 43
            $result = $stmt->fetch();

            if (!$result) {
                return Response::prepare(404, "Record not found");
            } else {
                return Response::prepare(200, "Record found", $result);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return Response::prepare(404, "Table not found");
            } else {
                return Response::prepare(400, "Record not found", null, $e->getMessage());
            }
        }
    }

    // Vložení nového záznamu
    public function insert(string $table, $data)
    {
        try {
            $data = $this->removeSystemColumns($data);
            $columns = implode(", ", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            $stmt = $this->pdo->prepare("INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})");
            $result = $stmt->execute($data);

            if ($result) {
                return Response::prepare(201, "Record created", ['id' => $this->pdo->lastInsertId()]);
            } else {
                return Response::prepare(400, "Record not created");
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return Response::prepare(404, "Table not found");
            } else {
                return Response::prepare(400, "Record not created", null, $e->getMessage());
            }
        }
    }

    // Aktualizace záznamu
    public function update(string $table, int $id, $data)
    {
        try {
            $data = $this->removeSystemColumns($data);
            $fields = implode(", ", array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
            $data['id'] = $id;
            $stmt = $this->pdo->prepare("UPDATE `{$table}` SET {$fields} WHERE id = :id");

            // Ošetřit, zda byl záznam nalezen
            if (!$stmt->execute($data)) {
                return Response::prepare(400, "Record not updated");
            }

            // Ošetřit, zda byl záznam změněn
            if ($stmt->rowCount() === 0) {
                return Response::prepare(400, "No change detected");
            }

            return Response::prepare(200, "Record updated");
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return Response::prepare(404, "Table not found");
            } else {
                return Response::prepare(400, "Record not updated", null, $e->getMessage());
            }
        }
    }

    // Smazání záznamu
    public function delete(string $table, int $id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = :id");
            if (!$stmt->execute(['id' => $id])) {
                return Response::prepare(400, "Record not deleted");
            } elseif ($stmt->rowCount() === 0) {
                return Response::prepare(404, "Record not found");
            } else {
                return Response::prepare(200, "Record deleted");
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return Response::prepare(404, "Table not found");
            } else {
                return Response::prepare(400, "Records not deleted", null, $e->getMessage());
            }
        }
    }

    public function getSchema(string $tableName)
    {
        try {
            $result = [];

            $stmt = $this->pdo->prepare("SHOW TABLE STATUS LIKE '$tableName';");
            $stmt->execute();

            $status = $stmt->fetch();

            $result['name'] = [$status['Name']];
            $result['comment'] = [$status['Comment']];

            $stmt = $this->pdo->prepare("SHOW FULL COLUMNS FROM `$tableName`;");
            $stmt->execute();

            $fields = $stmt->fetchAll();

            $columns = [];

            foreach ($fields as $field) {
                $type = $this->mapTypeToSimpleType($field['Type']);
                $columns[] = [
                    'name' => $field['Field'],
                    'type' => $type,
                    'options' => $type === 'enum' ? explode("','", trim($field['Type'], "enum('')")) : [],
                    'null' => $field['Null'] === 'YES',
                    'key' => $field['Key'],
                    'default' => $type == 'number' ? (float) $field['Default'] : $field['Default'],
                    'extra' => $field['Extra'],
                    'comment' => $field['Comment'],
                    'length' => $this->getLength($field['Type']),
                    // 'raw' => $field,
                ];
            }

            $result['columns'] = $columns;

            return Response::prepare(200, "Schema found", $result);
        } catch (PDOException $e) {
            return Response::prepare(400, "Schema not found", null, $e->getMessage());
        }
    }

// Funkce pro mapování datového typu
    private static function mapTypeToSimpleType($type)
    {
        // Odstranění případných bílých znaků a převod na lowercase pro snadnější porovnání
        $type = strtolower(trim($type));

        if (strpos($type, 'int') !== false) {
            return 'number';
        } elseif (strpos($type, 'varchar') !== false || strpos($type, 'char') !== false) {
            return 'string';
        } elseif (strpos($type, 'text') !== false) {
            return 'text';
        } elseif (strpos($type, 'date') !== false || strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            return 'Date';
        } elseif (strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false) {
            return 'number';
        } elseif (strpos($type, 'bool') !== false || strpos($type, 'boolean') !== false) {
            return 'boolean';
        } elseif (strpos($type, 'enum') !== false) {
            return 'enum';
        } elseif (strpos($type, 'set') !== false) {
            return 'string'; // Můžete také mapovat na union typy v TypeScriptu
        } else {
            return 'string'; // Default typ
        }
    }

// Funkce pro získání hodnot ENUM
    private static function getEnumValues(string $tableName, string $columnName, $pdo)
    {
        if ($pdo && $tableName && $columnName) {
            $query = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
            $stmt = $pdo->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && preg_match("/^enum\(‘(.*)’\)$/", $result['Type'], $matches)) {
                $enumValues = explode("','", $matches[1]);
                // Mapa pro TypeScript enum
                return 'enum {' . implode(', ', array_map(function ($value) {
                    return "'$value'";
                }, $enumValues)) . '}';
            }
        }
        return 'string'; // Default typ, pokud se něco nezdaří
    }

    private static function getLength($type)
    {
        if (preg_match('/char\((.*?)\)/', $type, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function getTables()
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // odstraní z asociativního pole $data klíče, které nejsou v poli $columns
    private function removeSystemColumns($data, $systemColumns = ['id', 'created_at', 'updated_at']): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, $systemColumns)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    // Funkce pro získání hashovaného hesla
    public function getHashedPassword(string $email)
    {
        $query = "SELECT password FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
