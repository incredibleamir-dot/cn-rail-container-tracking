<?php
namespace App\Controllers;
use App\Core\Request;
use App\Core\View;
use App\Models\Container;
use App\Services\TrackingService;

class DashboardController {

    public static function index() {
        $userId = $_SESSION['user_id'];
        $showArchived = Request::get('archived') === '1';
        $containers = Container::findByUser($userId, $showArchived);
        $activeCount = Container::countByUser($userId);
        $archivedCount = Container::countArchived($userId);
        $allTags = Container::getAllTags($userId);
        $filterTag = Request::get('tag', '');
        $groupField = Request::get('group', '');

        // Get shipment titles
        $shipmentIds = array_filter(array_unique(array_map(fn($c) => $c['shipment_id'] ?? null, $containers)));
        $shipmentTitles = [];
        if (!empty($shipmentIds)) {
            $ph = implode(',', array_fill(0, count($shipmentIds), '?'));
            $shipmentTitles = array_column(
                \App\Core\Database::getInstance()->fetchAll("SELECT id, title FROM shipments WHERE id IN ({$ph})", array_values($shipmentIds)),
                'title', 'id'
            );
        }

        // Eager-load latest snapshots for all containers
        $containerIds = array_map(fn($c) => $c['id'], $containers);
        $snapshots = Container::getLatestSnapshotsForIds($containerIds);

        // Group by shipment
        $groupedContainers = [];
        if ($groupField === 'shipment') {
            foreach ($containers as $c) {
                $key = $c['shipment_id'] ? ($shipmentTitles[$c['shipment_id']] ?? 'Shipment #' . $c['shipment_id']) : '(no shipment)';
                $groupedContainers[$key][] = $c;
            }
            ksort($groupedContainers);
        }

        View::page('dashboard/index', compact(
            'containers', 'activeCount', 'archivedCount', 'allTags', 'filterTag',
            'shipmentTitles', 'groupField', 'groupedContainers', 'showArchived', 'snapshots'
        ), 'Dashboard');
    }

    public static function track() {
        $userId = $_SESSION['user_id'];
        $trackAll = isset($_POST['track_all']);
        $shipmentId = Request::int('shipment_id');
        $containerIds = $_POST['container_ids'] ?? [];

        if ($trackAll) {
            $containers = Container::findByUser($userId, false);
        } elseif (!empty($containerIds)) {
            $ids = array_map('intval', array_filter($containerIds));
            $containers = [];
            foreach ($ids as $id) {
                $c = Container::findById($id);
                if ($c && $c['user_id'] == $userId) $containers[] = $c;
            }
        } else {
            $containerId = Request::int('container_id');
            $c = Container::findById($containerId);
            if ($c && $c['user_id'] == $userId) {
                $containers = [$c];
            } else {
                $_SESSION['flash_error'] = 'Container not found.';
                header('Location: /');
                return;
            }
        }

        if (empty($containers)) {
            $_SESSION['flash_error'] = 'No containers to track.';
            header('Location: /');
            return;
        }

        $result = TrackingService::trackContainers($containers, $userId);
        $_SESSION['flash_success'] = "{$result['tracked']} container(s) refreshed.";
        if (!empty($result['errors'])) {
            $_SESSION['flash_error'] = implode(', ', $result['errors']);
        }

        if ($shipmentId) {
            header('Location: /shipments/view?id=' . $shipmentId);
        } else {
            $query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: '';
            header('Location: /' . ($query ? '?' . $query : ''));
        }
        exit;
    }

    public static function trackSingle() {
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $isAjax = Request::isAjax();

        $result = TrackingService::trackSingle($containerId, $userId);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Container refreshed.';
        } else {
            $_SESSION['flash_error'] = $result['error'] ?? 'Could not refresh.';
        }
        header('Location: ' . Request::referer('/'));
        exit;
    }

    public static function archive() {
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $c = Container::findById($containerId);
        if ($c && $c['user_id'] == $userId) {
            Container::archive($containerId);
            $_SESSION['flash_success'] = 'Container archived.';
        }
        header('Location: /');
        exit;
    }

    public static function unarchive() {
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $c = Container::findById($containerId);
        if ($c && $c['user_id'] == $userId) {
            Container::unarchive($containerId);
            $_SESSION['flash_success'] = 'Container restored.';
        }
        header('Location: /?archived=1');
        exit;
    }
}