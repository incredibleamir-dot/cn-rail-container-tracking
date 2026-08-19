<?php
namespace App\Core;

class Database {
    private static ?self $instance = null;
    private \PDO $pdo;

    private function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public static function init(\PDO $pdo): void {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            throw new \RuntimeException('Database not initialized');
        }
        return self::$instance;
    }

    public function getPdo(): \PDO {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): void {
        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            $sets[] = "{$col} = ?";
            $values[] = $val;
        }
        $values = array_merge($values, $whereParams);
        $this->query("UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$where}", $values);
    }

    public function delete(string $table, string $where, array $params = []): void {
        $this->query("DELETE FROM {$table} WHERE {$where}", $params);
    }

    public function count(string $table, string $where, array $params = []): int {
        $row = $this->fetch("SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    public function lastInsertId(): int {
        return (int)$this->pdo->lastInsertId();
    }
}
