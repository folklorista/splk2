<?php
namespace App;

class RelationshipLoader
{
    private Database $db;
    private Logger $logger;

    public function __construct(Database $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Parse include parameter from query string
     * Example: "roles,groups,events" → ["roles", "groups", "events"]
     *
     * @param string|null $includeParam
     * @return array Array of relationship names to include
     * @throws \InvalidArgumentException If include parameter is invalid
     */
    public function parseInclude(?string $includeParam): array
    {
        if (!$includeParam) {
            return [];
        }

        // Split by comma and trim
        $relationships = array_map('trim', explode(',', $includeParam));

        // Filter empty strings
        $relationships = array_filter($relationships, fn($r) => !empty($r));

        if (empty($relationships)) {
            return [];
        }

        // Validate relationship names (alphanumeric and underscore only)
        foreach ($relationships as $relationship) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $relationship)) {
                throw new \InvalidArgumentException(
                    "Invalid relationship name: '$relationship'. Names must be alphanumeric."
                );
            }
        }

        return array_values($relationships);
    }

    /**
     * Load related data for a record
     *
     * @param string $tableName The main table name
     * @param array $record The record to load relationships for
     * @param array $relationships Array of relationship names to load
     * @return array Record with relationships loaded
     */
    public function loadRelationships(string $tableName, array $record, array $relationships): array
    {
        if (empty($relationships) || empty($record)) {
            return $record;
        }

        foreach ($relationships as $relationshipName) {
            try {
                $relatedData = $this->loadRelationship($tableName, $record, $relationshipName);
                if ($relatedData !== null) {
                    $record[$relationshipName] = $relatedData;
                }
            } catch (\Exception $e) {
                $this->logger->warning("Failed to load relationship", [
                    'table' => $tableName,
                    'relationship' => $relationshipName,
                    'error' => $e->getMessage(),
                ]);
                // Don't include this relationship if loading fails
            }
        }

        return $record;
    }

    /**
     * Load a single relationship for a record
     *
     * @param string $tableName The main table name
     * @param array $record The record
     * @param string $relationshipName The relationship to load
     * @return array|null Related data or null if not found
     * @throws \Exception If relationship cannot be loaded
     */
    private function loadRelationship(string $tableName, array $record, string $relationshipName): ?array
    {
        // Try to detect relationship type based on record structure
        // Pattern 1: {relationshipName}_id → has one/many
        // Pattern 2: {tableName}_{relationshipName} or {relationshipName}_{tableName} → many-to-many

        $fkField = $relationshipName . '_id';

        // Check if record has FK field (e.g., role_id, group_id)
        if (isset($record[$fkField]) && !empty($record[$fkField])) {
            // Has one - load single related record
            return $this->loadHasOne($relationshipName, $record[$fkField]);
        }

        // Try to find junction table for many-to-many
        $junctionTable = $this->findJunctionTable($tableName, $relationshipName);
        if ($junctionTable) {
            return $this->loadManyToMany($tableName, $record['id'], $junctionTable);
        }

        // Could not detect relationship
        throw new \Exception(
            "Could not detect relationship '$relationshipName' from table '$tableName'"
        );
    }

    /**
     * Load a single related record (has one relationship)
     *
     * @param string $tableName The related table name
     * @param mixed $id The ID of the related record
     * @return array|null The related record or null if not found
     */
    private function loadHasOne(string $tableName, $id): ?array
    {
        try {
            $result = $this->db->get($tableName, $id);
            if ($result && $result['status'] === 200) {
                return $result['data'];
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to load has-one relationship", [
                'table' => $tableName,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Load many-to-many related records
     *
     * @param string $mainTable The main table name
     * @param mixed $mainId The main record ID
     * @param string $junctionTable The junction/linking table name
     * @return array Array of related records
     */
    private function loadManyToMany(string $mainTable, $mainId, string $junctionTable): array
    {
        try {
            // Assume junction table format: {table1}_{table2}
            // e.g., users_groups, members_events
            $fkField = $mainTable . '_id';

            // Query junction table for matching records
            $result = $this->db->getAllWhere(
                $junctionTable,
                "$fkField = ?",
                [$mainId]
            );

            if (!$result || $result['status'] !== 200) {
                return [];
            }

            // Extract IDs of related records
            // Assume the other FK is named {otherTable}_id
            $otherTableName = $this->inferOtherTableName($junctionTable, $mainTable);
            $otherFkField = $otherTableName . '_id';

            $relatedIds = array_map(
                fn($record) => $record[$otherFkField] ?? null,
                $result['data']
            );

            // Filter null values
            $relatedIds = array_filter($relatedIds);

            if (empty($relatedIds)) {
                return [];
            }

            // Load all related records
            $relatedRecords = [];
            foreach ($relatedIds as $id) {
                $record = $this->loadHasOne($otherTableName, $id);
                if ($record) {
                    $relatedRecords[] = $record;
                }
            }

            return $relatedRecords;
        } catch (\Exception $e) {
            $this->logger->error("Failed to load many-to-many relationship", [
                'main_table' => $mainTable,
                'main_id' => $mainId,
                'junction_table' => $junctionTable,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Find the junction table for a many-to-many relationship
     * Tries patterns: {mainTable}_{relatedTable} and {relatedTable}_{mainTable}
     *
     * @param string $mainTable The main table name
     * @param string $relatedTableName The related table name or relationship name
     * @return string|null The junction table name or null if not found
     */
    private function findJunctionTable(string $mainTable, string $relatedTableName): ?string
    {
        // Try both possible orderings
        $candidates = [
            $mainTable . '_' . $relatedTableName,  // users_roles
            $relatedTableName . '_' . $mainTable,  // roles_users
        ];

        foreach ($candidates as $tableName) {
            try {
                // Try to check if table exists by attempting a limited query
                $result = $this->db->getAllWhere(
                    $tableName,
                    "1=0",  // Empty WHERE to check existence without loading data
                    []
                );

                // If we got a response (even empty), the table exists
                if ($result !== null && isset($result['status'])) {
                    return $tableName;
                }
            } catch (\Exception $e) {
                // Table doesn't exist, try next candidate
                continue;
            }
        }

        return null;
    }

    /**
     * Infer the other table name from a junction table
     * E.g., "users_groups" with main table "users" → "groups"
     *
     * @param string $junctionTableName
     * @param string $mainTableName
     * @return string
     */
    private function inferOtherTableName(string $junctionTableName, string $mainTableName): string
    {
        // Try to remove main table name from junction table name
        if (strpos($junctionTableName, $mainTableName . '_') === 0) {
            return substr($junctionTableName, strlen($mainTableName) + 1);
        }

        if (strpos($junctionTableName, '_' . $mainTableName) !== false) {
            return str_replace('_' . $mainTableName, '', $junctionTableName);
        }

        // Fallback: just return the junction table name (shouldn't happen)
        return $junctionTableName;
    }

    /**
     * Load relationships for multiple records
     *
     * @param string $tableName The table name
     * @param array $records Array of records
     * @param array $relationships Array of relationship names
     * @return array Array of records with relationships loaded
     */
    public function loadRelationshipsForRecords(
        string $tableName,
        array $records,
        array $relationships
    ): array {
        return array_map(
            fn($record) => $this->loadRelationships($tableName, $record, $relationships),
            $records
        );
    }
}
