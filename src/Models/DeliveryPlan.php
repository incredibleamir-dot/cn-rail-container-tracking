<?php
namespace App\Models;
use App\Core\Database;

class DeliveryPlan {
    private static function db(): Database { return Database::getInstance(); }

    public static function createOrUpdate(int $userId, int $containerId, ?string $date = null, ?string $time = null, ?string $notes = null, ?int $shipmentId = null, ?int $freeDays = null, ?int $useWorkingDays = null, ?int $dayOfInterchange = null): int {
        $existing = self::findByContainer($userId, $containerId);
        $fields = [
            'delivery_date' => $date, 'delivery_time' => $time,
            'notes' => $notes, 'shipment_id' => $shipmentId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($freeDays !== null) $fields['free_days'] = $freeDays;
        if ($useWorkingDays !== null) $fields['use_working_days'] = $useWorkingDays;
        if ($dayOfInterchange !== null) $fields['day_of_interchange'] = $dayOfInterchange;

        if ($existing) {
            self::db()->update('delivery_plans', $fields, 'id = ?', [$existing['id']]);
            \Debug::logAction('DELIVERY_PLAN_UPDATED', "container_id={$containerId}");
            return $existing['id'];
        }
        $fields['user_id'] = $userId;
        $fields['container_id'] = $containerId;
        if (!isset($fields['free_days'])) $fields['free_days'] = 0;
        if (!isset($fields['use_working_days'])) $fields['use_working_days'] = 1;
        if (!isset($fields['day_of_interchange'])) $fields['day_of_interchange'] = 0;
        $id = self::db()->insert('delivery_plans', $fields);
        \Debug::logAction('DELIVERY_PLAN_CREATED', "container_id={$containerId}");
        return $id;
    }

    public static function findByContainer(int $userId, int $containerId): ?array {
        return self::db()->fetch(
            'SELECT * FROM delivery_plans WHERE user_id = ? AND container_id = ?', [$userId, $containerId]
        );
    }

    public static function getByContainerIds(int $userId, array $containerIds): array {
        if (empty($containerIds)) return [];
        $placeholders = implode(',', array_fill(0, count($containerIds), '?'));
        $rows = self::db()->fetchAll(
            "SELECT * FROM delivery_plans WHERE user_id = ? AND container_id IN ({$placeholders})",
            array_merge([$userId], $containerIds)
        );
        $results = [];
        foreach ($rows as $row) { $results[$row['container_id']] = $row; }
        return $results;
    }

    public static function deleteByContainerId(int $containerId, int $userId): void {
        self::db()->delete('delivery_plans', 'container_id = ? AND user_id = ?', [$containerId, $userId]);
        \Debug::logAction('DELIVERY_PLAN_DELETED_BY_CONTAINER', "container_id={$containerId}");
    }

    public static function recalculateDetention(int $containerId, int $userId): void {
        $plan = self::findByContainer($userId, $containerId);
        if (!$plan) return;

        $etaDate = self::getEtaDate($containerId);
        $startDate = $etaDate;

        if ($startDate && !empty($plan['day_of_interchange'])) {
            $dt = new \DateTime($startDate);
            $dt->modify('+1 day');
            $startDate = $dt->format('Y-m-d');
        }

        $detentionDays = 0;
        if ($startDate) {
            $useWorking = !empty($plan['use_working_days']);
            $today = date('Y-m-d');
            $detentionDays = self::calculateDetentionDays($startDate, $today, $useWorking);
        }

        $freeDays = (int)($plan['free_days'] ?? 0);
        $netDetention = max(0, $detentionDays - $freeDays);

        self::db()->query(
            'UPDATE delivery_plans SET detention_days = ? WHERE id = ?',
            [$netDetention, $plan['id']]
        );
    }

    public static function getEtaDate(int $containerId): ?string {
        $row = self::db()->fetch(
            "SELECT eta_local FROM tracking_snapshots
             WHERE container_id = ? AND eta_local IS NOT NULL AND eta_local != ''
             ORDER BY checked_at DESC LIMIT 1",
            [$containerId]
        );
        if ($row && !empty($row['eta_local'])) {
            return substr($row['eta_local'], 0, 10);
        }
        return null;
    }

    public static function calculateDetentionDays(string $startDate, string $endDate, bool $workingDaysOnly = false): int {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        if ($end < $start) return 0;

        if (!$workingDaysOnly) {
            return (int)$start->diff($end)->days;
        }

        $count = 0;
        $current = clone $start;
        while ($current <= $end) {
            $dayOfWeek = (int)$current->format('N');
            if ($dayOfWeek <= 5) {
                $count++;
            }
            $current->modify('+1 day');
        }
        return $count;
    }
}
