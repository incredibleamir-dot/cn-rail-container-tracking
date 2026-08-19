<?php
namespace App\Models;
use App\Core\Database;

class Container {
    private static function db(): Database { return Database::getInstance(); }

    public static function findByUser(int $userId, bool $archived = false): array {
        return self::db()->fetchAll(
            'SELECT * FROM containers WHERE user_id = ? AND is_archived = ? ORDER BY added_at DESC',
            [$userId, $archived ? 1 : 0]
        );
    }

    public static function findById(int $id): ?array {
        return self::db()->fetch('SELECT * FROM containers WHERE id = ?', [$id]);
    }

    public static function updateMetadata(int $id, array $data): bool {
        $allowed = ['bill_of_lading', 'po_number', 'customer_name', 'destination', 'commodity', 'notes', 'tags', 'shipment_id'];
        $fields = []; $values = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[$f] = $data[$f]; }
        }
        if (empty($fields)) return false;
        self::db()->update('containers', $fields, 'id = ?', [$id]);
        \Debug::logAction('CONTAINER_METADATA_UPDATED', "id={$id}");
        return true;
    }

    public static function archive(int $id): void {
        self::db()->update('containers', ['is_archived' => 1], 'id = ?', [$id]);
        \Debug::logAction('CONTAINER_ARCHIVED', "id={$id}");
    }

    public static function unarchive(int $id): void {
        self::db()->update('containers', ['is_archived' => 0], 'id = ?', [$id]);
        \Debug::logAction('CONTAINER_UNARCHIVED', "id={$id}");
    }

    public static function delete(int $id): void {
        self::db()->delete('containers', 'id = ?', [$id]);
        \Debug::logAction('CONTAINER_DELETED', "id={$id}");
    }

    public static function updateLastChecked(int $id): void {
        self::db()->query('UPDATE containers SET last_checked_at = datetime("now") WHERE id = ?', [$id]);
    }

    public static function countByUser(int $userId): int {
        return self::db()->count('containers', 'user_id = ? AND is_archived = 0', [$userId]);
    }

    public static function countArchived(int $userId): int {
        return self::db()->count('containers', 'user_id = ? AND is_archived = 1', [$userId]);
    }

    public static function getAllTags(int $userId): array {
        $rows = self::db()->fetchAll(
            'SELECT tags FROM containers WHERE user_id = ? AND tags != "" AND is_archived = 0', [$userId]
        );
        $allTags = [];
        foreach ($rows as $row) {
            foreach (parseTagsString($row['tags']) as $tag) {
                $allTags[$tag] = ($allTags[$tag] ?? 0) + 1;
            }
        }
        asort($allTags);
        return array_reverse($allTags, true);
    }

    public static function getLatestSnapshot(int $containerId): ?array {
        return self::db()->fetch(
            'SELECT * FROM tracking_snapshots WHERE container_id = ? ORDER BY checked_at DESC LIMIT 1',
            [$containerId]
        );
    }

    public static function getLatestSnapshotsForIds(array $containerIds): array {
        if (empty($containerIds)) return [];
        $placeholders = implode(',', array_fill(0, count($containerIds), '?'));
        $rows = self::db()->fetchAll(
            "SELECT ts.* FROM tracking_snapshots ts
             INNER JOIN (SELECT container_id, MAX(checked_at) as max_at FROM tracking_snapshots WHERE container_id IN ({$placeholders}) GROUP BY container_id) latest
             ON ts.container_id = latest.container_id AND ts.checked_at = latest.max_at",
            $containerIds
        );
        $map = [];
        foreach ($rows as $row) { $map[(int)$row['container_id']] = $row; }
        return $map;
    }
}