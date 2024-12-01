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
    
            // Získání základních informací o tabulce
            $stmt = $this->pdo->prepare("SHOW TABLE STATUS LIKE '$tableName';");
            $stmt->execute();
            $status = $stmt->fetch();
    
            $result['name'] = [$status['Name']];
            $result['comment'] = [$status['Comment']];
    
            // Získání informací o sloupcích tabulky
            $stmt = $this->pdo->prepare("SHOW FULL COLUMNS FROM `$tableName`;");
            $stmt->execute();
            $fields = $stmt->fetchAll();
    
            // Příprava pole pro sloupce
            $columns = [];
    
            foreach ($fields as $field) {
                $type = $this->mapTypeToSimpleType($field['Type']);
                
                // Získání informací o cizím klíči pro tento sloupec
                $stmt_fk = $this->pdo->prepare("
                    SELECT 
                        kcu.CONSTRAINT_NAME AS FOREIGN_KEY_CONSTRAINT, 
                        kcu.REFERENCED_TABLE_NAME AS REFERENCED_TABLE, 
                        kcu.REFERENCED_COLUMN_NAME AS REFERENCED_COLUMN
                    FROM 
                        INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                    WHERE 
                        kcu.TABLE_SCHEMA = DATABASE() 
                        AND kcu.TABLE_NAME = :tableName
                        AND kcu.COLUMN_NAME = :columnName
                        AND kcu.REFERENCED_TABLE_NAME IS NOT NULL;
                ");
                $stmt_fk->execute([
                    ':tableName' => $tableName,
                    ':columnName' => $field['Field']
                ]);
                $foreignKey = $stmt_fk->fetch();
    
                // Získání informací o sloupcích, které odkazují na tento sloupec
                $stmt_ref = $this->pdo->prepare("
                    SELECT 
                        kcu.CONSTRAINT_NAME AS REFERENCE_CONSTRAINT,
                        kcu.TABLE_NAME AS REFERENCE_TABLE,
                        kcu.COLUMN_NAME AS REFERENCE_COLUMN
                    FROM 
                        INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                    WHERE 
                        kcu.TABLE_SCHEMA = DATABASE() 
                        AND kcu.REFERENCED_TABLE_NAME = :tableName
                        AND kcu.REFERENCED_COLUMN_NAME = :columnName;
                ");
                $stmt_ref->execute([
                    ':tableName' => $tableName,
                    ':columnName' => $field['Field']
                ]);
                $references = $stmt_ref->fetchAll();
    
                // Přidání informací do pole sloupců
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
                    'foreign_key' => $foreignKey ? [
                        'constraint' => $foreignKey['FOREIGN_KEY_CONSTRAINT'],
                        'referenced_table' => $foreignKey['REFERENCED_TABLE'],
                        'referenced_column' => $foreignKey['REFERENCED_COLUMN']
                    ] : null,
                    'references' => $references ? array_map(function($ref) {
                        return [
                            'constraint' => $ref['REFERENCE_CONSTRAINT'],
                            'table' => $ref['REFERENCE_TABLE'],
                            'column' => $ref['REFERENCE_COLUMN']
                        ];
                    }, $references) : []
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

    public function searchRecords(string $tableName, string $searchQuery)
    {
        // Získání sloupců, které mají FULLTEXT index
        $stmt = $this->pdo->prepare("SHOW INDEXES FROM $tableName WHERE Index_type = 'FULLTEXT'");
        $stmt->execute();
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        if (empty($indexes)) {
            return []; // Pokud tabulka nemá FULLTEXT indexy, vracíme prázdné pole
        }
    
        // Seznam sloupců s FULLTEXT indexem
        $fulltextColumns = array_map(function($index) {
            return $index['Column_name'];
        }, $indexes);
    
        // Vytváříme dynamický SQL dotaz pro FULLTEXT hledání
        // $searchQuery = $searchQuery; // Bez přidávání % pro MATCH AGAINST
        $columns = implode(", ", $fulltextColumns); // Spojení názvů sloupců pro MATCH
    
        // Sestavení dotazu pro MATCH AGAINST
        $sql = "SELECT * FROM $tableName WHERE MATCH($columns) AGAINST(:searchQuery IN NATURAL LANGUAGE MODE)";
        
        // Příprava a vykonání dotazu
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':searchQuery', $searchQuery);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    public function getForeignKeyOptions(string $referencedTable)
    {
        // Nejprve zkontrolujeme, zda tabulka obsahuje sloupec 'name'
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM $referencedTable LIKE 'name'");
        $stmt->execute();
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Pokud sloupec 'name' existuje, použijeme ho
        if ($column) {
            $stmt = $this->pdo->prepare("SELECT id, name FROM $referencedTable");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    
        // Pokud sloupec 'name' neexistuje, použijeme první index (např. kombinace first_name a last_name)
        $stmt = $this->pdo->prepare("SHOW INDEX FROM $referencedTable WHERE Key_name = 'first_name_last_name'");
        $stmt->execute();
        $index = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Pokud je index definován, použijeme sloupce z indexu
        if ($index) {
            $stmt = $this->pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM $referencedTable");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    
        // Pokud tabulka nemá ani 'name', ani vhodný index, vrátíme chybu
        return null;
    }
    
    
}
