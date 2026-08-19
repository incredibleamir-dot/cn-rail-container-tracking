<?php
namespace App\Controllers;
use App\Core\Request;
use App\Core\View;
use App\Models\Container;
use App\Models\TrackingHistory;

class ContainerController {

    public static function show() {
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('id');

        $container = Container::findById($containerId);
        if (!$container || $container['user_id'] != $userId) {
            $_SESSION['flash_error'] = 'Container not found.';
            header('Location: /');
            return;
        }

        $history = TrackingHistory::getHistory($containerId, 100);
        $latest = TrackingHistory::getLatest($containerId);
        $snapshotCount = count($history);

        View::page('dashboard/container', compact(
            'container', 'history', 'latest', 'snapshotCount'
        ), 'Container ' . $container['container_number']);
    }

    public static function list() {
        $userId = $_SESSION['user_id'];
        $allContainers = Container::findByUser($userId, false);
        $archivedContainers = Container::findByUser($userId, true);
        $allContainers = array_merge($allContainers, $archivedContainers);
        $groupField = Request::get('group', '');

        // Eager-load snapshots
        $ids = array_map(fn($c) => $c['id'], $allContainers);
        $snapshots = Container::getLatestSnapshotsForIds($ids);

        // Group by metadata field
        $metaFields = ['bill_of_lading', 'po_number', 'customer_name', 'destination', 'commodity'];
        $groupedContainers = [];
        if ($groupField && in_array($groupField, $metaFields)) {
            foreach ($allContainers as $c) {
                $key = $c[$groupField] ?: '(empty)';
                $groupedContainers[$key][] = $c;
            }
            ksort($groupedContainers);
        }

        View::page('dashboard/container_list', compact(
            'allContainers', 'groupField', 'groupedContainers', 'snapshots'
        ), 'Container List');
    }

    public static function quickTrack() {
        View::page('dashboard/quick_track', [], 'Quick Track');
    }

    public static function quickTrackLookup() {
        header('Content-Type: application/json');
        $input = Request::trim('container_number');
        if (empty($input)) {
            echo json_encode(['success' => false, 'error' => 'Enter a container number.']);
            exit;
        }
        $result = \App\Services\TrackingService::quickTrack($input);
        echo json_encode($result);
    }
}