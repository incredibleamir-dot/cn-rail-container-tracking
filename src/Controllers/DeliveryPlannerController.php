<?php
namespace App\Controllers;
use App\Core\Request;
use App\Core\View;
use App\Core\Database;
use App\Models\Container;
use App\Models\DeliveryPlan;
use App\Models\Shipment;

class DeliveryPlannerController {

    public static function index() {
        $userId = $_SESSION['user_id'];

        $containerIds = [];
        if (!empty($_POST['container_ids'])) {
            $ids = explode(',', $_POST['container_ids']);
            $containerIds = array_map('intval', array_filter($ids));
        }

        if (!empty($containerIds)) {
            $ph = implode(',', array_fill(0, count($containerIds), '?'));
            $allContainers = Database::getInstance()->fetchAll(
                "SELECT * FROM containers WHERE id IN ({$ph}) AND user_id = ?",
                array_merge($containerIds, [$userId])
            );
        } else {
            $allContainers = Container::findByUser($userId, false);
            $archivedContainers = Container::findByUser($userId, true);
            $allContainers = array_merge($allContainers, $archivedContainers);
        }

        $planContainerIds = array_map(fn($c) => $c['id'], $allContainers);
        $deliveryPlans = DeliveryPlan::getByContainerIds($userId, $planContainerIds);
        $shipments = Shipment::findByUser($userId);
        $shipmentTitles = [];
        foreach ($shipments as $s) { $shipmentTitles[$s['id']] = $s['title']; }
        $containerTracking = Container::getLatestSnapshotsForIds($planContainerIds);

        foreach ($deliveryPlans as $cid => $plan) {
            DeliveryPlan::recalculateDetention($cid, $userId);
        }
        $deliveryPlans = DeliveryPlan::getByContainerIds($userId, $planContainerIds);

        $filter = Request::get('filter', '');
        $filteredContainers = $allContainers;
        if ($filter === 'has-plan') {
            $filteredContainers = array_filter($allContainers, function ($c) use ($deliveryPlans) {
                return isset($deliveryPlans[$c['id']]);
            });
        } elseif ($filter === 'no-plan') {
            $filteredContainers = array_filter($allContainers, function ($c) use ($deliveryPlans) {
                return !isset($deliveryPlans[$c['id']]);
            });
        }

        View::page('delivery_planner/index', compact(
            'allContainers', 'filteredContainers', 'deliveryPlans', 'shipmentTitles',
            'containerTracking', 'filter'
        ), 'Delivery Planner');
    }

    public static function save() {
        header('Content-Type: application/json');
        $userId = $_SESSION['user_id'];
        $containerId = Request::int('container_id');
        $date = $_POST['delivery_date'] ?? null;
        $time = $_POST['delivery_time'] ?? null;
        $freeDays = isset($_POST['free_days']) ? (int)$_POST['free_days'] : null;
        $useWorkingDays = isset($_POST['use_working_days']) ? 1 : 0;
        $dayOfInterchange = isset($_POST['day_of_interchange']) ? 1 : 0;

        $container = Container::findById($containerId);
        if (!$container || $container['user_id'] != $userId) {
            echo json_encode(['success' => false, 'error' => 'Container not found']);
            exit;
        }

        if (empty($date) && empty($time) && $freeDays === null) {
            DeliveryPlan::deleteByContainerId($containerId, $userId);
            echo json_encode(['success' => true, 'action' => 'deleted']);
            exit;
        }

        DeliveryPlan::createOrUpdate($userId, $containerId, $date ?: null, $time ?: null, null, null, $freeDays, $useWorkingDays, $dayOfInterchange);
        DeliveryPlan::recalculateDetention($containerId, $userId);

        $plan = DeliveryPlan::findByContainer($userId, $containerId);
        echo json_encode([
            'success' => true,
            'detention_days' => $plan['detention_days'] ?? 0,
        ]);
    }
}
