<?php
// Database.php
class Database
{
    private $pdo;

    public function __construct($config)
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // Získání všech záznamů z tabulky
    public function getAll($table)
    {
        $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
        return $stmt->fetchAll();
    }

    // Získání jednoho záznamu podle ID
    public function getById($table, $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // Vložení nového záznamu
    public function insert($table, $data)
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    // Aktualizace záznamu
    public function update($table, $id, $data)
    {
        $fields = implode(", ", array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
        $data['id'] = $id;
        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$fields} WHERE id = :id");
        return $stmt->execute($data);
    }

    // Smazání záznamu
    public function delete($table, $id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // Ověření uživatelského jména a hesla
    public function verifyUser($table, $email, $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Ověříme, zda uživatel existuje a zda heslo odpovídá hashovanému heslu v databázi
        if ($user && password_verify($password, $user['password'])) {
            return $user; // Vracíme uživatele, pokud je vše v pořádku
        }

        return false; // Pokud uživatelské jméno neexistuje nebo heslo nesedí
    }

    public function getSchema($tableName)
    {
        $stmt = $this->pdo->prepare("DESCRIBE $tableName");
        $stmt->execute();

        $columns = $stmt->fetchAll();
        $schema = [];

        foreach ($columns as $column) {
            $type = $this->mapTypeToSimpleType($column['Type']);
            $schema[] = [
                'name' => $column['Field'],
                'type' => $type,
                'options' => $type === 'enum' ? explode("','", trim($column['Type'], "enum('')")) : [],
                'null' => $column['Null'] === 'YES',
                'key' => $column['Key'],
                'default' => $type == 'number' ? (float) $column['Default'] : $column['Default'],
                'extra' => $column['Extra'],
            ];
        }

        return $schema;
    }

// Funkce pro mapování datového typu
    private static function mapTypeToSimpleType($type, $tableName = null, $columnName = null, $pdo = null)
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
        }

        return 'string'; // Default typ
    }

// Funkce pro získání hodnot ENUM
    private static function getEnumValues($tableName, $columnName, $pdo)
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

}
