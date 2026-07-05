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

        $columns = array_keys($rows[0]);
        $this->ensureTableExists($table, $columns);

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnsSql = implode(', ', array_map(static fn (string $c): string => '"' . $c . '"', $columns));
        $sql = 'INSERT INTO "' . $table . '" (' . $columnsSql . ') VALUES (' . $placeholders . ')';

        $stmt = $this->pdo->prepare($sql);
        foreach ($rows as $row) {
            $stmt->execute(array_values($row));
        }
    }

    public function assertRowExists(string $table, array $conditions): void
    {
        [$whereSql, $params] = $this->buildWhere($conditions);
        $sql = 'SELECT COUNT(*) FROM "' . $table . '"' . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        Assert::assertGreaterThan(0, $count, 'Expected row was not found in table ' . $table);
    }

    public function assertRowCount(string $table, int $expected, array $conditions = []): void
    {
        [$whereSql, $params] = $this->buildWhere($conditions);
        $sql = 'SELECT COUNT(*) FROM "' . $table . '"' . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        Assert::assertSame($expected, $count, 'Unexpected row count in table ' . $table);
    }

    private function query(string $sql): PDOStatement
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Query failed: ' . $sql);
        }

        return $stmt;
    }

    private function ensureTableExists(string $table, array $columns): void
    {
        $defs = [];
        foreach ($columns as $column) {
            $type = $column === $table . '_id' || str_ends_with($column, '_id') ? 'INTEGER' : 'TEXT';
            $defs[] = '"' . $column . '" ' . $type;
        }
        $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (' . implode(', ', $defs) . ')';
        $this->pdo->exec($sql);
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
