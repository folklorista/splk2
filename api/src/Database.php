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

        $dsn       = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // Získání všech záznamů z tabulky se stránkováním a řazením
    public function getAll(
        string $table,
        string $whereClause = "",
        int $limit = null,
        int $offset = null,
        string $orderBy = null,
        string $orderDir = 'ASC',
        string $searchQuery = null,
        array $searchColumns = null
    ) {
        try {
            // Načtení sloupců tabulky (bez sloupce 'password')
            $stmt    = $this->pdo->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns     = array_filter($columns, fn($column) => $column['Field'] !== 'password');
            $columnNames = array_map(fn($column) => $column['Field'], $columns);
            $columnsList = implode(', ', $columnNames);
            $whereClause = '';

            if ($searchQuery !== null) {
                $schema = $this->getSchema($table);
                $searchableSolumns = $this->getSearchableColumns($schema);

                // Kontrola, zda jsou vybrané sloupce validní a vyloučení sloupců id, created_at, updated_at
            if ($searchColumns !== null) {
                    $searchColumns = array_filter($searchColumns, fn($col) => in_array($col, $columnNames) && in_array($col, $searchableSolumns));
            } else {
                    $searchColumns = array_filter($columnNames, fn($col) => in_array($col, $searchableSolumns));
            }

            // Zjištění, zda existuje FULLTEXT index
            $fulltextAvailable = false;
            $indexStmt         = $this->pdo->query("SHOW INDEX FROM `{$table}` WHERE Index_type = 'FULLTEXT'");
            $indexes           = $indexStmt->fetchAll(PDO::FETCH_ASSOC);
            if (! empty($indexes)) {
                $fulltextAvailable = true;
            }

                // Dekódování `$searchQuery`
                if ($searchQuery !== null) {
                    $searchQuery = urldecode($searchQuery);
                }

            // Sestavení WHERE podmínky pro vyhledávání
                if ($fulltextAvailable) {
                    // Použití FULLTEXT indexu
                    $searchClause = "MATCH(" . implode(',', $searchColumns) . ") AGAINST (:searchQuery IN BOOLEAN MODE)";
                } else {
                    // Použití LIKE pro běžné vyhledávání
                    $searchParts  = array_map(fn($col) => "`$col` LIKE :searchQuery", $searchColumns);
                    $searchClause = implode(' OR ', $searchParts);
                }
                $whereClause = ! empty($whereClause) ? "($whereClause) AND ($searchClause)" : $searchClause;
            }

            // Sestavení SQL dotazu
            $query = "SELECT {$columnsList} FROM `{$table}`";
            if (! empty($whereClause)) {
                $query .= " WHERE {$whereClause}";
            }

            // Přidání řazení
            if ($orderBy !== null && in_array($orderBy, $columnNames)) {
                $query .= " ORDER BY `{$orderBy}` " . ($orderDir === 'DESC' ? 'DESC' : 'ASC');
            }

            // Přidání stránkování
            if ($limit !== null) {
                $query .= " LIMIT {$limit} OFFSET " . ($offset ?? 0);
            }

            $stmt = $this->pdo->prepare($query);

            // Pokud je vyhledávání, připravíme hodnotu LIKE nebo FULLTEXT
            if ($searchQuery !== null) {
                $searchParam = $fulltextAvailable ? $searchQuery : "%$searchQuery%";
                $stmt->bindValue(':searchQuery', $searchParam, PDO::PARAM_STR);
            }

            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Výpočet celkového počtu záznamů
            $countQuery = "SELECT COUNT(*) as total FROM `{$table}`";
            if (! empty($whereClause)) {
                $countQuery .= " WHERE {$whereClause}";
            }
            $countStmt = $this->pdo->prepare($countQuery);

            if ($searchQuery !== null) {
                $countStmt->bindValue(':searchQuery', $searchParam, PDO::PARAM_STR);
            }

            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($result) {
                // Úprava hodnot pro sloupce typu tinyint(1)
                foreach ($result as &$row) {
                    foreach ($columns as $column) {
                        if ($column['Type'] === 'tinyint(1)' && array_key_exists($column['Field'], $row)) {
                            $value = $row[$column['Field']];
                            if (is_null($value)) {
                                $row[$column['Field']] = null; // Zachování null
                            } else {
                                $row[$column['Field']] = $value == 0 ? false : true; // 0 -> false, jinak true
                            }
                        }
                    }
                }
            }

            // Přidání meta informací
            $meta = [];
            if ($limit !== null) {
                $meta['pagination'] = [
                    'limit'         => $limit,
                    'offset'        => $offset ?? 0,
                    'total_records' => $totalRecords,
                    'total_pages'   => ceil($totalRecords / $limit),
                ];
            }
            if ($orderBy !== null) {
                $meta['sorting'] = [
                    'order_by'  => $orderBy,
                    'direction' => $orderDir,
                ];
            }
            if ($searchQuery !== null) {
                $meta['search'] = [
                    'query'    => $searchQuery,
                    'columns'  => $searchColumns,
                    'fulltext' => $fulltextAvailable,
                ];
            }

            return Response::prepare(
                statusCode: ! empty($result) ? 200 : 204,
                message: ! empty($result) ? "Records found" : "No records found",
                data: $result,
                error: null,
                meta: $meta
            );

        } catch (PDOException $e) {
            return Response::prepare(500, "Database error", null, $e->getMessage());
        }
    }

    // Získání jednoho záznamu podle ID
    public function get(string $table, string | int $id, string $key = 'id')
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE {$key} = :id");
            $stmt->execute(['id' => $id]); // line 43
            $result = $stmt->fetch();

            if (! $result) {
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

            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $data[$key] = $value ? 1 : 0;
                }
            }

            $columns      = implode(", ", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            $stmt         = $this->pdo->prepare("INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})");
            $result       = $stmt->execute($data);

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

            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $data[$key] = $value ? 1 : 0;
                }
            }

            $fields     = implode(", ", array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
            $data['id'] = $id;
            $stmt       = $this->pdo->prepare("UPDATE `{$table}` SET {$fields} WHERE id = :id");

            // Ošetřit, zda byl záznam nalezen
            if (! $stmt->execute($data)) {
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
            if (! $stmt->execute(['id' => $id])) {
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

            $result['name']    = [$status['Name']];
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
                    ':tableName'  => $tableName,
                    ':columnName' => $field['Field'],
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
                    ':tableName'  => $tableName,
                    ':columnName' => $field['Field'],
                ]);
                $references = $stmt_ref->fetchAll();

                // Přidání informací do pole sloupců
                $columns[] = [
                    'name'        => $field['Field'],
                    'type'        => $type,
                    'options'     => $this->getOptions($type, $field),
                    'null'        => $field['Null'] === 'YES',
                    'key'         => $field['Key'],
                    'default'     => $this->getDefaultValue($type, $field),
                    'extra'       => $field['Extra'],
                    'comment'     => $field['Comment'],
                    'length'      => $this->getLength($field['Type']),
                    'foreign_key' => $foreignKey ? [
                        'constraint'        => $foreignKey['FOREIGN_KEY_CONSTRAINT'],
                        'referenced_table'  => $foreignKey['REFERENCED_TABLE'],
                        'referenced_column' => $foreignKey['REFERENCED_COLUMN'],
                    ] : null,
                    'references'  => $references ? array_map(function ($ref) {
                        return [
                            'constraint' => $ref['REFERENCE_CONSTRAINT'],
                            'table'      => $ref['REFERENCE_TABLE'],
                            'column'     => $ref['REFERENCE_COLUMN'],
                        ];
                    }, $references) : [],
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

        if (strpos($type, 'bool') !== false || strpos($type, 'boolean') !== false || strpos($type, 'tinyint(1)') !== false) {
            return 'boolean';
        } elseif (strpos($type, 'int') !== false) {
            return 'number';
        } elseif (strpos($type, 'varchar') !== false || strpos($type, 'char') !== false) {
            return 'string';
        } elseif (strpos($type, 'text') !== false) {
            return 'text';
        } elseif (strpos($type, 'date') !== false) {
            return 'date';
        } elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            return 'datetime';
        } elseif (strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false) {
            return 'number';
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
            $query  = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
            $stmt   = $pdo->query($query);
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
            if (! in_array($key, $systemColumns)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    // Funkce pro získání hashovaného hesla
    public function getHashedPassword(string $email)
    {
        $query = "SELECT password FROM users WHERE email = :email";
        $stmt  = $this->pdo->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function searchRecords(string $tableName, string $searchQuery)
    {
        // Získání sloupců, které mají FULLTEXT index
        $stmt = $this->pdo->prepare("SHOW INDEXES FROM `$tableName` WHERE Index_type = 'FULLTEXT'");
        $stmt->execute();
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($indexes)) {
            return []; // Pokud tabulka nemá FULLTEXT indexy, vracíme prázdné pole
        }

        // Seznam sloupců s FULLTEXT indexem
        $fulltextColumns = array_map(function ($index) {
            return $index['Column_name'];
        }, $indexes);

                                                    // Vytváříme dynamický SQL dotaz pro FULLTEXT hledání
                                                    // $searchQuery = $searchQuery; // Bez přidávání % pro MATCH AGAINST
        $columns = implode(", ", $fulltextColumns); // Spojení názvů sloupců pro MATCH

        // Sestavení dotazu pro MATCH AGAINST
        $sql = "SELECT * FROM `$tableName` WHERE MATCH($columns) AGAINST(:searchQuery IN NATURAL LANGUAGE MODE)";

        // Příprava a vykonání dotazu
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':searchQuery', $searchQuery);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getForeignKeyOptions(string $referencedTable)
    {
        // Kontrola přítomnosti stromové struktury (sloupce 'parent_id' a 'position')
        $stmtParentId = $this->pdo->prepare("SHOW COLUMNS FROM `$referencedTable` LIKE 'parent_id'");
        $stmtParentId->execute();
        $parentColumn = $stmtParentId->fetch(PDO::FETCH_ASSOC);

        $stmtPosition = $this->pdo->prepare("SHOW COLUMNS FROM `$referencedTable` LIKE 'position'");
        $stmtPosition->execute();
        $positionColumn = $stmtPosition->fetch(PDO::FETCH_ASSOC);

        $isTree  = $parentColumn && $positionColumn;
        $orderBy = $isTree ? " ORDER BY position" : "";

        if ($isTree) {
            $stmt = $this->pdo->prepare("SELECT id FROM `$referencedTable` $orderBy");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $row) {
                $id = $row['id'];
                $this->pdo->exec("CALL `get_tree_path`('$referencedTable', $id, @`fullPath`)");
                $pathStmt = $this->pdo->query("SELECT " . "@" . "`fullPath` AS name");
                $path     = $pathStmt->fetch(PDO::FETCH_ASSOC);
                $data[]   = ['id' => $id, 'name' => $path['name']];
            }

            return $data;
        }

        // Kontrola přítomnosti sloupce 'name'
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$referencedTable` LIKE 'name'");
        $stmt->execute();
        $column = $stmt->fetch(PDO::FETCH_ASSOC);

        // Pokud sloupec 'name' existuje, použijeme ho
        if ($column) {
            $selectColumns = "id, name";
            $stmt          = $this->pdo->prepare("SELECT $selectColumns FROM `$referencedTable` $orderBy");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Kontrola speciálního indexu '_name'
        $stmt = $this->pdo->prepare("SHOW INDEX FROM `$referencedTable` WHERE Key_name = '_name'");
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            // Pokud tabulka nemá ani 'name', ani vhodný index, vrátíme chybu
            return null;
        }

        // Získání všech column_name z indexu
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index) {
            $columns[] = $index['Column_name'];
        }

        $columnsToConcat = [];
        $joins           = [''];

        $data = [];
        foreach ($columns as &$indexedColumn) {
            if ($indexedColumn === 'category_id') {
                $joins[]           = "categories ON (`" . $referencedTable . "`.category_id = categories.id)";
                $columnsToConcat[] = "`categories`.`prefix`";
            } elseif ($indexedColumn === 'group_id') {
                $joins[]           = "groups ON (`" . $referencedTable . "`.group_id = groups.id)";
                $columnsToConcat[] = "`groups`.`name`";
            } else {
                $columnsToConcat[] = "`" . $referencedTable . "`.`" . $indexedColumn . "`";
            }
        }

        $query = "SELECT `"
        . $referencedTable
        . "`.`id`, CONCAT("
        . join(", ' ', ", $columnsToConcat)
        . ") as name FROM `"
        . $referencedTable .
        "` "
        . join(" JOIN ", $joins)
            . $orderBy;

        Logger::log($query);

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function addIndentation(array $data): array
    {
        $levels = [];
        $result = [];

        foreach ($data as $row) {
            $id       = $row['id'];
            $parentId = $row['parent_id'] ?? null;

            // Výpočet úrovně
            $level = 0;
            if ($parentId !== null && isset($levels[$parentId])) {
                $level = $levels[$parentId] + 1;
            }
            $levels[$id] = $level;

            // Přidání odsazení k 'name'
            $row['name'] = str_repeat('—', $level) . ' ' . $row['name'];

            // Odstranění sloupce 'parent_id'
            unset($row['parent_id']);

            $result[] = $row;
        }

        return $result;
    }

    public function isForeignKey(string $table, string $column)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                kcu.REFERENCED_TABLE_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            WHERE
                kcu.TABLE_SCHEMA = DATABASE()
                AND kcu.TABLE_NAME = :table
                AND kcu.COLUMN_NAME = :column
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL;
        ");
        $stmt->execute([
            ':table'  => $table,
            ':column' => $column,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    // Tree structures

    public function getAllTreeRecords(string $tableName): array
    {
        $stmt = $this->pdo->prepare("SELECT id, parent_id, name FROM `$tableName` ORDER BY position ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function treeRecordExists(string $tableName, int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() > 0;
    }

    public function insertTreeRecord(string $tableName, array $treeRecord): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO `$tableName` (name, parent_id, position, created_at, updated_at)
                  VALUES (:name, :parent_id, :position, NOW(), NOW())");
        $stmt->execute($treeRecord);

        // Vrátíme ID vloženého záznamu
        return $this->pdo->lastInsertId();
    }

    public function updateTreeRecord(string $tableName, array $treeRecord): void
    {
        $stmt = $this->pdo->prepare("UPDATE `$tableName`
                  SET name = :name, parent_id = :parent_id, position = :position, updated_at = NOW()
                  WHERE id = :id");
        $stmt->execute($treeRecord);
    }

    // Audit log

    public function logAction(
        AuditAction $actionType,
        ?int $userId = null,
        ?string $tableName = null,
        ?int $recordId = null,
        ?string $details = null,
        ?array $data = null
    ): void {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $jsonData  = $data ? json_encode($data) : null;

        $stmt = $this->pdo->prepare("
        INSERT INTO audit_logs
            (action_id, user_id, table_name, record_id, details, data, ip_address, user_agent)
        VALUES
            (:action_id, :user_id, :table_name, :record_id, :details, :data, :ip_address, :user_agent)
    ");
        $stmt->execute([
            'action_id'  => $actionType->value,
            'user_id'    => $userId,
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'details'    => $details,
            'data'       => $jsonData,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    private function getDefaultValue($type, $field): mixed
    {
        switch ($type) {
            case 'number':
                return (float) $field['Default'];
            case 'boolean':
                return (bool) $field['Default'];
            default:
                return $field['Default'];
        }
    }

    private function getOptions($type, $field): array | bool
    {
        switch ($type) {
            case 'enum':
                return explode("','", trim($field['Type'], "enum('')"));
            default:
                return [];
        }
    }

    private function getSearchableColumns(array $schema): array {
        $filteredColumns = [];
    
        if (!isset($schema['data']['columns']) || !is_array($schema['data']['columns'])) {
            return $filteredColumns;
        }
    
        foreach ($schema['data']['columns'] as $column) {
            // Podmínky pro vyloučení sloupců
            if (
                ($column['key'] ?? '') === 'PRI' ||   // Primární klíč
                ($column['foreign_key'] ?? '') ||     // Cizí klíč
                ($column['type'] ?? '') === 'datetime' || // Datetime
                ($column['type'] ?? '') === 'boolean' ||  // Boolean
                ($column['name'] ?? '') === 'password'    // Password
            ) {
                continue;
            }
    
            $filteredColumns[] = $column['name'];
        }
    
        return $filteredColumns;
    }
}
