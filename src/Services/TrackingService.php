<?php
namespace App\Services;

use App\Models\Container;
use App\Models\TrackingHistory;
use App\Models\Settings;

class TrackingService {

    public static function trackContainers(array $containers, int $userId): array {
        $dataSource = Settings::get('data_source', 'cn_api');
        $gasUrl = Settings::get('gas_url', '');

        if ($dataSource === 'google_sheet' && !empty($gasUrl)) {
            return self::trackFromGoogleSheet($containers, $userId, $gasUrl);
        }
        return self::trackFromCnApi($containers, $userId);
    }

    private static function trackFromGoogleSheet(array $containers, int $userId, string $gasUrl): array {
        $sheetData = fetchGoogleSheetData($gasUrl);
        $tracked = 0;
        foreach ($containers as $c) {
            if (!empty($c['is_archived'])) continue;
            $norm = containerNormalizeForAPI($c['container_number']);
            if (isset($sheetData[$norm])) {
                $parsed = $sheetData[$norm];
                TrackingHistory::saveSnapshot($c['id'], $userId, $c['container_number'], $parsed, null, null);
                Container::updateLastChecked($c['id']);
                $event = strtolower($parsed['lastEvent'] ?? '');
                if (strpos($event, 'out-gate') !== false || strpos($event, 'out gate') !== false) {
                    Container::archive($c['id']);
                    \Debug::logAction('AUTO_ARCHIVE', "OUT-GATE: {$c['container_number']}");
                }
                $tracked++;
            }
        }
        \Debug::logAction('TRACK_SHEET', "tracked={$tracked} containers");
        return ['tracked' => $tracked, 'errors' => []];
    }

    private static function trackFromCnApi(array $containers, int $userId): array {
        $token = getCNAuthToken();
        if (!$token) {
            return ['tracked' => 0, 'errors' => ['Unable to connect to CN API']];
        }

        $activeContainers = [];
        foreach ($containers as $c) {
            if (!empty($c['is_archived'])) {
                \Debug::logAction('SKIP_ARCHIVED', "SKIP: {$c['container_number']} (already archived)");
                continue;
            }
            $activeContainers[] = $c;
        }

        if (empty($activeContainers)) {
            return ['tracked' => 0, 'errors' => []];
        }

        $apiIds = [];
        $normToContainer = [];
        foreach ($activeContainers as $c) {
            $norm = containerNormalizeForAPI($c['container_number']);
            $apiIds[] = $norm;
            $normToContainer[$norm] = $c;
        }
        $apiIds = array_unique($apiIds);

        $gpsMap = fetchBatchGps($apiIds, $token);
        $trackingMap = fetchBatchTracking($apiIds, $token);

        $tracked = 0;
        foreach ($activeContainers as $c) {
            $norm = containerNormalizeForAPI($c['container_number']);
            if (isset($trackingMap[$norm])) {
                $parsed = parseAPIResponse($trackingMap[$norm], $c['container_number']);
                $gps = $gpsMap[$norm] ?? null;
                TrackingHistory::saveSnapshot($c['id'], $userId, $c['container_number'], $parsed, $gps, $trackingMap[$norm]);
                Container::updateLastChecked($c['id']);

                $event = strtolower($parsed['lastEvent'] ?? '');
                if (strpos($event, 'out-gate') !== false || strpos($event, 'out gate') !== false) {
                    Container::archive($c['id']);
                    \Debug::logAction('AUTO_ARCHIVE', "OUT-GATE: {$c['container_number']}");
                }
                $tracked++;
            }
        }
        \Debug::logAction('TRACK', "tracked={$tracked} containers");
        return ['tracked' => $tracked, 'errors' => []];
    }

    public static function trackSingle(int $containerId, int $userId): array {
        $c = Container::findById($containerId);
        if (!$c || $c['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Not found'];
        }

        if (!empty($c['is_archived'])) {
            return ['success' => false, 'error' => 'Container is archived (OUT-GATE)'];
        }

        $dataSource = Settings::get('data_source', 'cn_api');
        $gasUrl = Settings::get('gas_url', '');

        if ($dataSource === 'google_sheet' && !empty($gasUrl)) {
            $sheetData = fetchGoogleSheetData($gasUrl, [$c['container_number']]);
            $norm = containerNormalizeForAPI($c['container_number']);
            if (isset($sheetData[$norm])) {
                $parsed = $sheetData[$norm];
                TrackingHistory::saveSnapshot($c['id'], $userId, $c['container_number'], $parsed, null, null);
                Container::updateLastChecked($c['id']);
                $event = strtolower($parsed['lastEvent'] ?? '');
                if (strpos($event, 'out-gate') !== false || strpos($event, 'out gate') !== false) {
                    Container::archive($c['id']);
                }
                return ['success' => true, 'data' => $parsed];
            }
            return ['success' => false, 'error' => 'Container not found in Google Sheet'];
        }

        $token = getCNAuthToken();
        if (!$token) {
            return ['success' => false, 'error' => 'CN API auth failed'];
        }

        $norm = containerNormalizeForAPI($c['container_number']);
        $gpsMap = fetchBatchGps([$norm], $token);
        $trackingMap = fetchBatchTracking([$norm], $token);

        if (isset($trackingMap[$norm])) {
            $parsed = parseAPIResponse($trackingMap[$norm], $c['container_number']);
            $gps = $gpsMap[$norm] ?? null;
            TrackingHistory::saveSnapshot($c['id'], $userId, $c['container_number'], $parsed, $gps, $trackingMap[$norm]);
            Container::updateLastChecked($c['id']);
            $event = strtolower($parsed['lastEvent'] ?? '');
            if (strpos($event, 'out-gate') !== false || strpos($event, 'out gate') !== false) {
                Container::archive($c['id']);
                \Debug::logAction('AUTO_ARCHIVE', "OUT-GATE: {$c['container_number']}");
            }
            return ['success' => true, 'data' => $parsed];
        }

        return ['success' => false, 'error' => 'No data from CN API'];
    }

    public static function quickTrack(string $containerNumber): array {
        $clean = containerNormalize($containerNumber);
        $validation = containerValidate($clean);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => "Invalid: {$validation['reason']}"];
        }

        $dataSource = Settings::get('data_source', 'cn_api');
        $gasUrl = Settings::get('gas_url', '');

        if ($dataSource === 'google_sheet' && !empty($gasUrl)) {
            $sheetData = fetchGoogleSheetData($gasUrl, [$clean]);
            $norm = containerNormalizeForAPI($clean);
            if (isset($sheetData[$norm])) {
                return ['success' => true, 'data' => $sheetData[$norm], 'gps' => null];
            }
            return ['success' => false, 'error' => 'Container not found in Google Sheet'];
        }

        $token = getCNAuthToken();
        if (!$token) {
            return ['success' => false, 'error' => 'CN API auth failed'];
        }

        $norm = containerNormalizeForAPI($clean);
        $gpsMap = fetchBatchGps([$norm], $token);
        $trackingMap = fetchBatchTracking([$norm], $token);

        if (isset($trackingMap[$norm])) {
            $parsed = parseAPIResponse($trackingMap[$norm], $clean);
            $gps = $gpsMap[$norm] ?? null;
            return ['success' => true, 'data' => $parsed, 'gps' => $gps];
        }

        return ['success' => false, 'error' => 'No data from CN API'];
    }
}
