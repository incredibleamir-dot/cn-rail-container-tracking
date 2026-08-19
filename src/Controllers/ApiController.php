<?php
namespace App\Controllers;
use App\Core\Request;
use App\Models\Container;
use App\Models\TrackingHistory;

class ApiController {

    public static function getContainer() {
        header('Content-Type: application/json');
        self::requireAuth();
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $container = Container::findById($containerId);
        if (!$container || $container['user_id'] != $userId) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }
        echo json_encode($container);
    }

    public static function saveMetadata() {
        header('Content-Type: application/json');
        self::requireAuth();
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $container = Container::findById($containerId);
        if (!$container || $container['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not found']);
            return;
        }
        $allowed = ['bill_of_lading', 'po_number', 'customer_name', 'destination', 'commodity', 'notes', 'tags'];
        $fields = [];
        foreach ($allowed as $f) {
            if (isset($_POST[$f])) $fields[$f] = trim($_POST[$f]);
        }
        Container::updateMetadata($containerId, $fields);
        echo json_encode(['success' => true]);
    }

    public static function getHistory() {
        header('Content-Type: application/json');
        self::requireAuth();
        $containerId = Request::int('container_id');
        if (!$containerId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing container_id']);
            return;
        }
        $history = TrackingHistory::getHistory($containerId, 50);
        $normalized = [];
        foreach ($history as $row) {
            $normalized[] = [
                'check_date' => $row['checked_at'],
                'status' => $row['waybill_status'] ?? 'N/A',
                'location' => $row['last_event_location'] ?? '-',
                'event' => $row['last_event'] ?? '-',
                'event_time' => $row['last_event_time_local'] ?? null,
                'eta' => $row['eta_local'] ?? null,
                'customs' => $row['customs_status'] ?? null,
            ];
        }
        echo json_encode($normalized);
    }

    public static function toggleArchive() {
        header('Content-Type: application/json');
        self::requireAuth();
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $container = Container::findById($containerId);
        if (!$container || $container['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not found']);
            return;
        }
        if ($container['is_archived']) {
            Container::unarchive($containerId);
            echo json_encode(['success' => true, 'archived' => false]);
        } else {
            Container::archive($containerId);
            echo json_encode(['success' => true, 'archived' => true]);
        }
    }

    public static function deleteContainer() {
        header('Content-Type: application/json');
        self::requireAuth();
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $container = Container::findById($containerId);
        if (!$container || $container['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not found']);
            return;
        }
        Container::delete($containerId);
        echo json_encode(['success' => true]);
    }

    private static function requireAuth(): void {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }
}