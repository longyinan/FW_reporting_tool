<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

class LegacyPostgresSchema
{
    private string $connection;

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(string $connection)
    {
        $this->connection = $connection;
    }

    public function hasColumn(string $tableName, string $columnName): bool
    {
        [$schemaName, $pureTableName] = $this->parseTableName($tableName);
        if ($pureTableName === null || !$this->isValidIdentifier($pureTableName) || !$this->isValidIdentifier($columnName)) {
            return false;
        }

        if ($schemaName !== null && !$this->isValidIdentifier($schemaName)) {
            return false;
        }

        $cacheKey = ($schemaName ?? '*') . '.' . $pureTableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $sql = <<<'SQL'
SELECT 1
FROM pg_catalog.pg_attribute a
JOIN pg_catalog.pg_class c ON c.oid = a.attrelid
JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
WHERE c.relkind = 'r'
  AND c.relname = ?
  AND a.attname = ?
  AND a.attnum > 0
  AND a.attisdropped = FALSE
SQL;
        $bindings = [$pureTableName, $columnName];

        if ($schemaName === null) {
            $sql .= "\n  AND pg_catalog.pg_table_is_visible(c.oid)";
        } else {
            $sql .= "\n  AND n.nspname = ?";
            $bindings[] = $schemaName;
        }

        $sql .= "\nLIMIT 1";

        $exists = DB::connection($this->connection)->selectOne($sql, $bindings) !== null;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseTableName(string $tableName): array
    {
        $parts = explode('.', $tableName);

        if (count($parts) === 1) {
            return [null, $parts[0]];
        }

        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }

        return [null, null];
    }

    private function isValidIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }
}
