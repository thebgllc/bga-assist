<?php

declare(strict_types=1);

namespace BgaHarness;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Assert;

class BgaDatabaseFake
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function DbQuery(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function getCollectionFromDB(string $sql, bool $bUniqueValue = false): array
    {
        $stmt = $this->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$bUniqueValue) {
            return $rows;
        }

        $unique = [];
        foreach ($rows as $row) {
            $values = array_values($row);
            if (count($values) >= 2) {
                $unique[$values[0]] = $values[1];
            }
        }

        return $unique;
    }

    public function getObjectFromDB(string $sql): ?array
    {
        $stmt = $this->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getUniqueValueFromDB(string $sql): mixed
    {
        $stmt = $this->query($sql);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public function getIntFromDB(string $sql): int
    {
        return (int) $this->getUniqueValueFromDB($sql);
    }

    public function seedTable(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = $this->collectColumns($rows);
        $this->ensureTableHasColumns($table, $columns, $rows);

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnsSql = implode(', ', array_map(static fn (string $c): string => '"' . $c . '"', $columns));
        $sql = 'INSERT INTO "' . $table . '" (' . $columnsSql . ') VALUES (' . $placeholders . ')';

        $stmt = $this->pdo->prepare($sql);
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row[$column] ?? null;
            }
            $stmt->execute($values);
        }
    }

    public function assertRowExists(string $table, array $conditions): void
    {
        [$whereSql, $params] = $this->buildWhere($conditions);
        $sql = 'SELECT COUNT(*) FROM "' . $table . '"' . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        Assert::assertGreaterThan(
            0,
            $count,
            sprintf('Expected row was not found in table "%s" for conditions: %s', $table, json_encode($conditions))
        );
    }

    public function assertRowCount(string $table, int $expected, array $conditions = []): void
    {
        [$whereSql, $params] = $this->buildWhere($conditions);
        $sql = 'SELECT COUNT(*) FROM "' . $table . '"' . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        Assert::assertSame(
            $expected,
            $count,
            sprintf(
                'Unexpected row count in table "%s" for conditions %s. Expected %d, got %d.',
                $table,
                json_encode($conditions),
                $expected,
                $count
            )
        );
    }

    private function query(string $sql): PDOStatement
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Query failed: ' . $sql);
        }

        return $stmt;
    }

    private function collectColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    private function ensureTableHasColumns(string $table, array $columns, array $rows): void
    {
        if (!$this->tableExists($table)) {
            $this->createTable($table, $columns, $rows);

            return;
        }

        $existing = $this->listColumns($table);
        foreach ($columns as $column) {
            if (in_array($column, $existing, true)) {
                continue;
            }
            $type = $this->inferColumnType($table, $column, $rows);
            $this->pdo->exec('ALTER TABLE "' . $table . '" ADD COLUMN "' . $column . '" ' . $type);
        }
    }

    private function createTable(string $table, array $columns, array $rows): void
    {
        $defs = [];
        foreach ($columns as $column) {
            $defs[] = '"' . $column . '" ' . $this->inferColumnType($table, $column, $rows);
        }
        $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (' . implode(', ', $defs) . ')';
        $this->pdo->exec($sql);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private function listColumns(string $table): array
    {
        $stmt = $this->query('PRAGMA table_info("' . $table . '")');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    private function inferColumnType(string $table, string $column, array $rows): string
    {
        if ($column === $table . '_id' || str_ends_with($column, '_id')) {
            return 'INTEGER';
        }

        foreach ($rows as $row) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }

            if (is_int($row[$column]) || is_bool($row[$column])) {
                return 'INTEGER';
            }

            if (is_float($row[$column])) {
                return 'REAL';
            }
        }

        return 'TEXT';
    }

    private function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $clauses = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $clauses[] = '"' . $column . '" = ?';
            $params[] = $value;
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
