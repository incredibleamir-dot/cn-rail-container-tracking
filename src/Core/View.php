<?php
namespace App\Core;

class View {
    public static function render(string $viewPath, array $data = []): void {
        extract($data);
        $file = __DIR__ . '/../../views/' . $viewPath . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("View not found: {$viewPath}");
        }
        ob_start();
        require $file;
        ob_end_clean();
    }

    public static function layout(string $layout, array $data = []): void {
        extract($data);
        $file = __DIR__ . '/../../views/layout/' . $layout . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        require $file;
    }

    public static function page(string $viewPath, array $data = [], ?string $pageTitle = null): void {
        $data['pageTitle'] = $pageTitle ?? ($data['pageTitle'] ?? 'Dashboard');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        $pageMap = [
            '/' => 'dashboard',
            '/containers' => 'containers',
            '/quick-track' => 'quick-track',
            '/shipments' => 'shipments',
            '/delivery-planner' => 'delivery-planner',
            '/analysis' => 'analysis',
            '/admin' => 'admin',
            '/admin/settings' => 'admin',
        ];
        $data['currentPage'] = $pageMap[$path] ?? 'dashboard';
        $data['showArchived'] = isset($_GET['archived']) && $_GET['archived'] === '1';

        // Flash messages
        $data['flashSuccess'] = $_SESSION['flash_success'] ?? null;
        $data['flashError'] = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $data['contentView'] = $viewPath;

        extract($data);
        ob_start();
        require __DIR__ . '/../../views/layout/main.php';
        $output = ob_get_clean();
        echo $output;
    }
}