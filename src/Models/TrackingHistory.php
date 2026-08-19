<?php
namespace App\Models;
use App\Core\Database;

class TrackingHistory {
    private static function db(): Database { return Database::getInstance(); }

    public static function saveSnapshot(int $containerId, int $userId, string $containerNumber, array $data, ?array $gps = null, ?array $rawApi = null): ?int {
        $existing = self::getLatest($containerId);
        if ($existing &&
            ($existing['waybill_status'] ?? '') === ($data['waybillStatus'] ?? '') &&
            ($existing['last_event'] ?? '') === ($data['lastEvent'] ?? '') &&
            ($existing['last_event_time_local'] ?? '') === ($data['lastEventTimeLocal'] ?? '')) {
            return null;
        }

        $etaLocal = $data['etaLocal'] ?? null;
        $etaTimezone = $data['etaTimezone'] ?? null;
        $etaStation = $data['etaStation'] ?? null;
        $lastFreeDay = $data['lastFreeDay'] ?? null;

        if ($existing) {
            if (empty($etaLocal) && !empty($existing['eta_local'])) {
                $etaLocal = $existing['eta_local'];
                $etaTimezone = $existing['eta_timezone'];
                $etaStation = $existing['eta_station'];
            }
            if (empty($lastFreeDay) && !empty($existing['last_free_day'])) {
                $lastFreeDay = $existing['last_free_day'];
            }
        }

        $id = self::db()->insert('tracking_snapshots', [
            'container_id' => $containerId,
            'user_id' => $userId,
            'container_number' => $containerNumber,
            'waybill_status' => $data['waybillStatus'] ?? null,
            'load_empty' => $data['loadEmpty'] ?? null,
            'last_event' => $data['lastEvent'] ?? null,
            'last_event_time_local' => $data['lastEventTimeLocal'] ?? null,
            'last_event_timezone' => $data['lastEventTimezone'] ?? null,
            'last_event_location' => $data['lastEventLocation'] ?? null,
            'eta_local' => $etaLocal,
            'eta_timezone' => $etaTimezone,
            'eta_station' => $etaStation,
            'last_free_day' => $lastFreeDay,
            'customs_status' => $data['customsStatus'] ?? null,
            'customs_timestamp' => $data['customsTimestamp'] ?? null,
            'gps_latitude' => $gps['Latitude'] ?? null,
            'gps_longitude' => $gps['Longitude'] ?? null,
            'gps_speed' => $gps['Speed'] ?? null,
            'raw_api_response' => $rawApi ? json_encode($rawApi) : null,
        ]);

        self::trimOld($containerId);

        $event = strtolower($data['lastEvent'] ?? '');
        if (strpos($event, 'de-ramp') !== false || strpos($event, 'deramp') !== false) {
            DeliveryPlan::recalculateDetention($containerId, $userId);
        }

        return $id;
    }

    public static function getLatest(int $containerId): ?array {
        return self::db()->fetch(
            'SELECT * FROM tracking_snapshots WHERE container_id = ? ORDER BY checked_at DESC LIMIT 1',
            [$containerId]
        );
    }

    public static function getHistory(int $containerId, int $limit = 50): array {
        return self::db()->fetchAll(
            'SELECT * FROM tracking_snapshots WHERE container_id = ? ORDER BY checked_at DESC LIMIT ?',
            [$containerId, $limit]
        );
    }

    public static function trimOld(int $containerId, int $keep = 50): void {
        self::db()->query(
            'DELETE FROM tracking_snapshots WHERE container_id = ? AND id NOT IN (SELECT id FROM tracking_snapshots WHERE container_id = ? ORDER BY checked_at DESC LIMIT ?)',
            [$containerId, $containerId, $keep]
        );
    }

    public static function getLatestRawApi(int $containerId): ?array {
        $latest = self::getLatest($containerId);
        if ($latest && !empty($latest['raw_api_response'])) {
            return json_decode($latest['raw_api_response'], true);
        }
        return null;
    }
}