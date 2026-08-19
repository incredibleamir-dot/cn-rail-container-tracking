<?php
namespace App\Controllers;
use App\Core\Request;
use App\Core\View;
use App\Models\Container;
use App\Models\Shipment;
use App\Services\TrackingService;

class ShipmentController {

    public static function list() {
        $userId = $_SESSION['user_id'];
        $showArchived = Request::get('archived') === '1';
        $shipments = Shipment::findByUser($userId, $showArchived);
        View::page('shipments/list', compact('shipments', 'showArchived'), 'Shipments');
    }

    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_SESSION['user_id'];
            $data = [
                'title' => Request::trim('title'),
                'bill_of_lading' => Request::trim('bill_of_lading'),
                'po_number' => Request::trim('po_number'),
                'customer_name' => Request::trim('customer_name'),
                'destination' => Request::trim('destination'),
                'commodity' => Request::trim('commodity'),
                'notes' => Request::trim('notes'),
                'tags' => Request::trim('tags'),
            ];
            $shipment = Shipment::create($userId, $data);

            if (!empty($_POST['containers'])) {
                $parts = self::parseContainerInput($_POST['containers']);
                $result = Shipment::addContainers($shipment['id'], $userId, $parts);
                Shipment::copyMetadataToContainers($shipment['id']);
                if (!empty($result['added'])) {
                    TrackingService::trackContainers($result['added'], $userId);
                }
                $msg = "Shipment created. " . count($result['added']) . " container(s) added.";
                if (count($result['invalid']) > 0) {
                    $invalidNums = array_map(fn($i) => $i['number'] . ' (' . $i['reason'] . ')', $result['invalid']);
                    $msg .= " " . count($result['invalid']) . " invalid: " . implode(', ', array_slice($invalidNums, 0, 3));
                }
                $_SESSION['flash_success'] = $msg;
            } else {
                $_SESSION['flash_success'] = 'Shipment created. Add containers to it.';
            }
            header('Location: /shipments/add?id=' . $shipment['id']);
            exit;
        }
        View::page('shipments/create', [], 'Create Shipment');
    }

    public static function add() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('id');
        $shipment = Shipment::findById($shipmentId);
        if (!$shipment || $shipment['user_id'] != $userId) {
            $_SESSION['flash_error'] = 'Shipment not found.';
            header('Location: /shipments');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = trim($_POST['containers'] ?? '');
            if (empty($input)) {
                $_SESSION['flash_error'] = 'Please enter container numbers.';
                header('Location: /shipments/add?id=' . $shipmentId);
                return;
            }
            $parts = self::parseContainerInput($input);
            $result = Shipment::addContainers($shipmentId, $userId, $parts);
            Shipment::copyMetadataToContainers($shipmentId);
            $allNew = array_merge($result['added'], $result['existing']);
            if (!empty($allNew)) TrackingService::trackContainers($allNew, $userId);

            $msg = count($result['added']) . " container(s) added, " . count($result['existing']) . " already in shipment.";
            if (count($result['invalid']) > 0) {
                $invalidNums = array_map(fn($i) => $i['number'] . ' (' . $i['reason'] . ')', $result['invalid']);
                $msg .= " " . count($result['invalid']) . " invalid: " . implode(', ', array_slice($invalidNums, 0, 3));
            }
            $_SESSION['flash_success'] = $msg;
            header('Location: /shipments/add?id=' . $shipmentId);
            exit;
        }

        $containers = Shipment::getContainers($shipmentId);
        $containerIds = array_map(fn($c) => $c['id'], $containers);
        $containerTracking = Container::getLatestSnapshotsForIds($containerIds);

        View::page('shipments/add', compact('shipment', 'containers', 'containerTracking'), 'Add Containers');
    }

    public static function view() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('id');
        $shipment = Shipment::findById($shipmentId);
        if (!$shipment || $shipment['user_id'] != $userId) {
            $_SESSION['flash_error'] = 'Shipment not found.';
            header('Location: /shipments');
            return;
        }

        $containers = Shipment::getContainers($shipmentId);
        $containerIds = array_map(fn($c) => $c['id'], $containers);
        $containerTracking = Container::getLatestSnapshotsForIds($containerIds);

        View::page('shipments/view', compact('shipment', 'shipmentId', 'containers', 'containerTracking'), $shipment['title'] ?: 'Shipment #' . $shipmentId);
    }

    public static function edit() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('id');
        $shipment = Shipment::findById($shipmentId);
        if (!$shipment || $shipment['user_id'] != $userId) {
            $_SESSION['flash_error'] = 'Shipment not found.';
            header('Location: /shipments');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => Request::trim('title'),
                'bill_of_lading' => Request::trim('bill_of_lading'),
                'po_number' => Request::trim('po_number'),
                'customer_name' => Request::trim('customer_name'),
                'destination' => Request::trim('destination'),
                'commodity' => Request::trim('commodity'),
                'notes' => Request::trim('notes'),
                'tags' => Request::trim('tags'),
            ];
            Shipment::update($shipmentId, $data);
            $_SESSION['flash_success'] = 'Shipment updated.';
            header('Location: /shipments/view?id=' . $shipmentId);
            exit;
        }

        View::page('shipments/edit', compact('shipment'), 'Edit Shipment');
    }

    public static function delete() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('id');
        $shipment = Shipment::findById($shipmentId);
        if ($shipment && $shipment['user_id'] == $userId) {
            Shipment::delete($shipmentId);
            $_SESSION['flash_success'] = 'Shipment deleted.';
        }
        header('Location: /shipments');
        exit;
    }

    public static function archive() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('id');
        $shipment = Shipment::findById($shipmentId);
        if ($shipment && $shipment['user_id'] == $userId) {
            Shipment::archive($shipmentId);
            $_SESSION['flash_success'] = 'Shipment archived.';
        }
        header('Location: /shipments');
        exit;
    }

    public static function unarchive() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('id');
        $shipment = Shipment::findById($shipmentId);
        if ($shipment && $shipment['user_id'] == $userId) {
            Shipment::unarchive($shipmentId);
            $_SESSION['flash_success'] = 'Shipment restored.';
        }
        header('Location: /shipments?archived=1');
        exit;
    }

    public static function unlink() {
        $userId = $_SESSION['user_id'];
        $shipmentId = Request::int('shipment_id');
        $containerId = Request::int('container_id');

        $shipment = Shipment::findById($shipmentId);
        if (!$shipment || $shipment['user_id'] != $userId) {
            $_SESSION['flash_error'] = 'Shipment not found.';
            header('Location: /shipments');
            return;
        }

        \App\Models\Shipment::unlinkContainer($containerId);
        $_SESSION['flash_success'] = 'Container removed from shipment.';
        header('Location: /shipments/view?id=' . $shipmentId);
        exit;
    }

    private static function parseContainerInput(string $input): array {
        $cleanInput = str_replace(['/', '-', '|'], ' ', $input);
        $parts = preg_split('/[,\s\n\r]+/', $cleanInput);
        return array_unique(array_filter(array_map('strtoupper', array_map('trim', $parts))));
    }
}