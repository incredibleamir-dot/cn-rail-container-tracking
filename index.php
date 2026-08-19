<?php
require_once __DIR__ . '/bootstrap.php';

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ContainerController;
use App\Controllers\ShipmentController;
use App\Controllers\DeliveryPlannerController;
use App\Controllers\AnalysisController;
use App\Controllers\AdminController;
use App\Controllers\ApiController;

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'doLogin']);
$router->any('/logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);
$router->post('/track', [DashboardController::class, 'track']);
$router->post('/track-single', [DashboardController::class, 'trackSingle']);
$router->post('/archive', [DashboardController::class, 'archive']);
$router->post('/unarchive', [DashboardController::class, 'unarchive']);

// Containers
$router->get('/container', [ContainerController::class, 'show']);
$router->get('/containers', [ContainerController::class, 'list']);
$router->get('/quick-track', [ContainerController::class, 'quickTrack']);
$router->post('/quick-track/lookup', [ContainerController::class, 'quickTrackLookup']);

// Shipments
$router->get('/shipments', [ShipmentController::class, 'list']);
$router->get('/shipments/create', [ShipmentController::class, 'create']);
$router->post('/shipments/create', [ShipmentController::class, 'create']);
$router->get('/shipments/add', [ShipmentController::class, 'add']);
$router->post('/shipments/add', [ShipmentController::class, 'add']);
$router->get('/shipments/view', [ShipmentController::class, 'view']);
$router->get('/shipments/edit', [ShipmentController::class, 'edit']);
$router->post('/shipments/edit', [ShipmentController::class, 'edit']);
$router->post('/shipments/delete', [ShipmentController::class, 'delete']);
$router->post('/shipments/unlink', [ShipmentController::class, 'unlink']);
$router->post('/shipments/archive', [ShipmentController::class, 'archive']);
$router->post('/shipments/unarchive', [ShipmentController::class, 'unarchive']);

// Delivery Planner
$router->get('/delivery-planner', [DeliveryPlannerController::class, 'index']);
$router->post('/delivery-planner', [DeliveryPlannerController::class, 'index']);
$router->post('/delivery-planner/save', [DeliveryPlannerController::class, 'save']);

// Analysis
$router->get('/analysis', [AnalysisController::class, 'index']);

// Admin
$router->get('/admin', [AdminController::class, 'users']);
$router->post('/admin/create', [AdminController::class, 'createUser']);
$router->post('/admin/edit', [AdminController::class, 'editUser']);
$router->post('/admin/toggle', [AdminController::class, 'toggleUser']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->post('/admin/settings/save', [AdminController::class, 'saveSettings']);

// API endpoints
$router->get('/api/container', [ApiController::class, 'getContainer']);
$router->post('/api/save-metadata', [ApiController::class, 'saveMetadata']);
$router->get('/api/history', [ApiController::class, 'getHistory']);
$router->post('/api/toggle-archive', [ApiController::class, 'toggleArchive']);
$router->post('/api/delete-container', [ApiController::class, 'deleteContainer']);

// 404 handler
$router->notFound(function () {
    http_response_code(404);
    require __DIR__ . '/views/errors/404.php';
});

// Auth guard (after route matching but before dispatch)
$publicRoutes = ['/login', '/logout'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if (!in_array($path, $publicRoutes) && empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Admin guard
$adminRoutes = ['/admin', '/admin/settings'];
if (in_array($path, $adminRoutes) && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    require __DIR__ . '/views/errors/403.php';
    exit;
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);