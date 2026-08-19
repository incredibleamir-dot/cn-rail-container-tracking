<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Database;

class AnalysisController {

    public static function index() {
        $userId = $_SESSION['user_id'];
        $pdo = Database::getInstance()->getPdo();

        $totalContainers = 0; $activeContainers = 0; $archivedContainers = 0;
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt, is_archived FROM containers WHERE user_id = ? GROUP BY is_archived');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['is_archived']) $archivedContainers = (int)$row['cnt'];
            else $activeContainers = (int)$row['cnt'];
        }
        $totalContainers = $activeContainers + $archivedContainers;

        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM shipments WHERE user_id = ?');
        $stmt->execute([$userId]);
        $totalShipments = (int)$stmt->fetch()['cnt'];

        $statusData = self::getLatestFieldGroup($pdo, $userId, 'waybill_status');
        $customsData = self::getLatestFieldGroup($pdo, $userId, 'customs_status');
        $loadData = self::getLatestFieldGroup($pdo, $userId, 'load_empty');
        $topCustomers = self::getContainerFieldGroup($pdo, $userId, 'customer_name', 6);
        $topDestinations = self::getContainerFieldGroup($pdo, $userId, 'destination', 6);
        $topLocations = self::getLatestFieldGroup($pdo, $userId, 'last_event_location', 6);
        $byShipment = self::getByShipment($pdo, $userId, 6);
        $lfdAlerts = self::getLfdAlerts($pdo, $userId);
        $upcomingEtas = self::getUpcomingEtas($pdo, $userId);
        $deliveryStats = self::getDeliveryStats($pdo, $userId);
        $trackingActivity = self::getTrackingActivity($pdo, $userId);

        View::page('analysis/index', compact(
            'totalContainers', 'activeContainers', 'archivedContainers', 'totalShipments',
            'statusData', 'customsData', 'loadData', 'topCustomers', 'topDestinations',
            'topLocations', 'byShipment', 'lfdAlerts', 'upcomingEtas', 'deliveryStats', 'trackingActivity'
        ), 'Analysis');
    }

    private static function getLatestFieldGroup($pdo, int $userId, string $field, int $limit = 0): array {
        $sql = "SELECT ts.{$field} as val, COUNT(*) as cnt
                FROM tracking_snapshots ts
                INNER JOIN (SELECT container_id, MAX(checked_at) as max_at FROM tracking_snapshots WHERE user_id = ? GROUP BY container_id) latest
                ON ts.container_id = latest.container_id AND ts.checked_at = latest.max_at
                WHERE ts.user_id = ? AND ts.{$field} IS NOT NULL AND ts.{$field} != ''
                GROUP BY ts.{$field} ORDER BY cnt DESC";
        if ($limit > 0) $sql .= " LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        $results = [];
        foreach ($stmt->fetchAll() as $row) { $results[$row['val'] ?: '(empty)'] = (int)$row['cnt']; }
        return $results;
    }

    private static function getContainerFieldGroup($pdo, int $userId, string $field, int $limit = 0): array {
        $sql = "SELECT {$field} as val, COUNT(*) as cnt
                FROM containers WHERE user_id = ? AND is_archived = 0 AND {$field} IS NOT NULL AND {$field} != ''
                GROUP BY {$field} ORDER BY cnt DESC";
        if ($limit > 0) $sql .= " LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $results = [];
        foreach ($stmt->fetchAll() as $row) { $results[$row['val'] ?: '(empty)'] = (int)$row['cnt']; }
        return $results;
    }

    private static function getByShipment($pdo, int $userId, int $limit = 0): array {
        $sql = "SELECT COALESCE(s.title, 'No Shipment') as val, COUNT(*) as cnt
                FROM containers c LEFT JOIN shipments s ON s.id = c.shipment_id
                WHERE c.user_id = ? GROUP BY c.shipment_id ORDER BY cnt DESC";
        if ($limit > 0) $sql .= " LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $results = [];
        foreach ($stmt->fetchAll() as $row) { $results[$row['val']] = (int)$row['cnt']; }
        return $results;
    }

    private static function getLfdAlerts($pdo, int $userId): array {
        $sql = "SELECT c.container_number, ts.last_free_day, ts.last_event_location
                FROM tracking_snapshots ts
                INNER JOIN (SELECT container_id, MAX(checked_at) as max_at FROM tracking_snapshots WHERE user_id = ? GROUP BY container_id) latest ON ts.container_id = latest.container_id AND ts.checked_at = latest.max_at
                INNER JOIN containers c ON c.id = ts.container_id
                WHERE ts.user_id = ? AND ts.last_free_day IS NOT NULL AND ts.last_free_day != '' AND ts.last_free_day <= date('now', '+3 days')
                ORDER BY ts.last_free_day ASC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    private static function getUpcomingEtas($pdo, int $userId): array {
        $sql = "SELECT c.container_number, ts.eta_local, ts.eta_station, ts.last_event_location
                FROM tracking_snapshots ts
                INNER JOIN (SELECT container_id, MAX(checked_at) as max_at FROM tracking_snapshots WHERE user_id = ? GROUP BY container_id) latest ON ts.container_id = latest.container_id AND ts.checked_at = latest.max_at
                INNER JOIN containers c ON c.id = ts.container_id
                WHERE ts.user_id = ? AND ts.eta_local IS NOT NULL AND ts.eta_local != ''
                AND ts.eta_local >= datetime('now') AND ts.eta_local <= datetime('now', '+7 days')
                ORDER BY ts.eta_local ASC LIMIT 15";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    private static function getDeliveryStats($pdo, int $userId): array {
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM containers WHERE user_id = ? AND is_archived = 0');
        $stmt->execute([$userId]);
        $total = (int)$stmt->fetch()['cnt'];
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT dp.container_id) as cnt FROM delivery_plans dp INNER JOIN containers c ON c.id = dp.container_id WHERE dp.user_id = ? AND c.is_archived = 0');
        $stmt->execute([$userId]);
        $planned = (int)$stmt->fetch()['cnt'];
        return ['total' => $total, 'planned' => $planned, 'unplanned' => $total - $planned];
    }

    private static function getTrackingActivity($pdo, int $userId): array {
        $sql = "SELECT date(checked_at) as day, COUNT(*) as cnt
                FROM tracking_snapshots WHERE user_id = ? AND checked_at >= date('now', '-30 days')
                GROUP BY date(checked_at) ORDER BY day ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $results = [];
        foreach ($stmt->fetchAll() as $row) { $results[$row['day']] = (int)$row['cnt']; }
        return $results;
    }
}