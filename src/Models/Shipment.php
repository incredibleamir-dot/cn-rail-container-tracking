<?php
namespace App\Models;
use App\Core\Database;

class Shipment {
    private static function db(): Database { return Database::getInstance(); }

    public static function create(int $userId, array $data): array {
        $id = self::db()->insert('shipments', [
            'user_id' => $userId,
            'title' => $data['title'] ?? '',
            'bill_of_lading' => $data['bill_of_lading'] ?? '',
            'po_number' => $data['po_number'] ?? '',
            'customer_name' => $data['customer_name'] ?? '',
            'destination' => $data['destination'] ?? '',
            'commodity' => $data['commodity'] ?? '',
            'notes' => $data['notes'] ?? '',
            'tags' => $data['tags'] ?? '',
        ]);
        \Debug::logAction('SHIPMENT_CREATED', "id={$id}");
        return self::findById($id);
    }

    public static function findByUser(int $userId, bool $archived = false): array {
        return self::db()->fetchAll(
            'SELECT s.*, (SELECT COUNT(*) FROM containers c WHERE c.shipment_id = s.id AND c.user_id = ?) as container_count
             FROM shipments s WHERE s.user_id = ? AND s.is_archived = ? ORDER BY s.created_at DESC',
            [$userId, $userId, $archived ? 1 : 0]
        );
    }

    public static function findById(int $id): ?array {
        return self::db()->fetch('SELECT * FROM shipments WHERE id = ?', [$id]);
    }

    public static function update(int $id, array $data): bool {
        $allowed = ['title', 'bill_of_lading', 'po_number', 'customer_name', 'destination', 'commodity', 'notes', 'tags'];
        $fields = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }
        if (empty($fields)) return false;
        $fields['updated_at'] = date('Y-m-d H:i:s');
        self::db()->update('shipments', $fields, 'id = ?', [$id]);
        \Debug::logAction('SHIPMENT_UPDATED', "id={$id}");
        return true;
    }

    public static function archive(int $id): void {
        self::db()->update('shipments', ['is_archived' => 1], 'id = ?', [$id]);
        \Debug::logAction('SHIPMENT_ARCHIVED', "id={$id}");
    }

    public static function unarchive(int $id): void {
        self::db()->update('shipments', ['is_archived' => 0], 'id = ?', [$id]);
        \Debug::logAction('SHIPMENT_UNARCHIVED', "id={$id}");
    }

    public static function delete(int $id): void {
        self::db()->query('UPDATE containers SET shipment_id = NULL WHERE shipment_id = ?', [$id]);
        self::db()->delete('shipments', 'id = ?', [$id]);
        \Debug::logAction('SHIPMENT_DELETED', "id={$id}");
    }

    public static function getContainers(int $shipmentId): array {
        return self::db()->fetchAll(
            'SELECT * FROM containers WHERE shipment_id = ? ORDER BY added_at DESC', [$shipmentId]
        );
    }

    public static function linkContainer(int $shipmentId, int $containerId): void {
        self::db()->update('containers', ['shipment_id' => $shipmentId], 'id = ?', [$containerId]);
        \Debug::logAction('SHIPMENT_LINK_CONTAINER', "shipment={$shipmentId} container={$containerId}");
    }

    public static function unlinkContainer(int $containerId): void {
        self::db()->query('UPDATE containers SET shipment_id = NULL WHERE id = ?', [$containerId]);
        \Debug::logAction('SHIPMENT_UNLINK_CONTAINER', "container={$containerId}");
    }

    public static function addContainers(int $shipmentId, int $userId, array $containerNumbers): array {
        $added = []; $invalid = []; $existing = [];
        foreach ($containerNumbers as $input) {
            $clean = containerNormalize($input);
            if (empty($clean)) continue;
            $validation = containerValidate($clean);
            if (!$validation['valid']) {
                $invalid[] = ['number' => $clean, 'reason' => $validation['reason']];
                continue;
            }
            $row = self::db()->fetch('SELECT * FROM containers WHERE user_id = ? AND container_number = ?', [$userId, $clean]);
            if ($row) {
                if ($row['shipment_id'] != $shipmentId) self::linkContainer($shipmentId, $row['id']);
                $existing[] = $row;
                continue;
            }
            $newId = self::db()->insert('containers', ['user_id' => $userId, 'container_number' => $clean, 'shipment_id' => $shipmentId]);
            $added[] = self::db()->fetch('SELECT * FROM containers WHERE id = ?', [$newId]);
            \Debug::logAction('CONTAINER_ADDED_TO_SHIPMENT', "number={$clean} shipment={$shipmentId}");
        }
        return ['added' => $added, 'invalid' => $invalid, 'existing' => $existing];
    }

    public static function copyMetadataToContainers(int $shipmentId): void {
        $shipment = self::findById($shipmentId);
        if (!$shipment) return;
        $containers = self::getContainers($shipmentId);
        if (empty($containers)) return;
        $metaFields = ['bill_of_lading', 'po_number', 'customer_name', 'destination', 'commodity'];
        $updateData = [];
        foreach ($metaFields as $f) {
            if (!empty($shipment[$f])) $updateData[$f] = $shipment[$f];
        }
        if (empty($updateData)) return;
        foreach ($containers as $c) { Container::updateMetadata($c['id'], $updateData); }
        \Debug::logAction('SHIPMENT_COPY_META', "shipment={$shipmentId} containers=" . count($containers));
    }
}