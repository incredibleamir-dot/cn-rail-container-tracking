<?php
namespace App\Models;
use App\Core\Database;

class User {
    private static function db(): Database { return Database::getInstance(); }

    public static function findByPin(string $pin): ?array {
        return self::db()->fetch('SELECT * FROM users WHERE pin = ? AND is_active = 1', [$pin]);
    }

    public static function findById(int $id): ?array {
        return self::db()->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findAll(bool $includeInactive = false): array {
        $sql = 'SELECT * FROM users';
        if (!$includeInactive) $sql .= ' WHERE is_active = 1';
        $sql .= ' ORDER BY created_at DESC';
        return self::db()->fetchAll($sql);
    }

    public static function countAll(): int { return self::db()->count('users', '1=1'); }
    public static function countActive(): int { return self::db()->count('users', 'is_active = 1'); }

    public static function create(string $name, string $pin, string $role = 'user'): int {
        $id = self::db()->insert('users', ['name' => $name, 'pin' => $pin, 'role' => $role]);
        \Debug::logAction('USER_CREATED', "name={$name} role={$role}");
        return $id;
    }

    public static function update(int $id, string $name, ?string $pin = null, ?string $role = null): void {
        $data = ['name' => $name];
        if ($pin !== null) $data['pin'] = $pin;
        if ($role !== null) $data['role'] = $role;
        self::db()->update('users', $data, 'id = ?', [$id]);
        \Debug::logAction('USER_UPDATED', "id={$id}");
    }

    public static function toggleActive(int $id): void {
        self::db()->query('UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
        \Debug::logAction('USER_TOGGLED', "id={$id}");
    }

    public static function pinExists(string $pin, ?int $excludeId = null): bool {
        if ($excludeId) {
            $row = self::db()->fetch('SELECT COUNT(*) as cnt FROM users WHERE pin = ? AND id != ?', [$pin, $excludeId]);
        } else {
            $row = self::db()->fetch('SELECT COUNT(*) as cnt FROM users WHERE pin = ?', [$pin]);
        }
        return (int)($row['cnt'] ?? 0) > 0;
    }
}