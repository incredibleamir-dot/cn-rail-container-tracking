<?php
/**
 * CN Track - PSR-4 Style Autoloader
 */

spl_autoload_register(function ($class) {
    $baseDir = __DIR__;

    $classMap = [
        'App\\Models\\User'             => 'src/Models/User.php',
        'App\\Models\\Container'        => 'src/Models/Container.php',
        'App\\Models\\TrackingHistory'  => 'src/Models/TrackingHistory.php',
        'App\\Models\\Settings'         => 'src/Models/Settings.php',
        'App\\Models\\Shipment'         => 'src/Models/Shipment.php',
        'App\\Models\\DeliveryPlan'     => 'src/Models/DeliveryPlan.php',
        'App\\Core\\Database'           => 'src/Core/Database.php',
        'App\\Core\\Router'             => 'src/Core/Router.php',
        'App\\Core\\Request'            => 'src/Core/Request.php',
        'App\\Core\\View'               => 'src/Core/View.php',
        'App\\Services\\TrackingService'=> 'src/Services/TrackingService.php',
        'App\\Controllers\\AuthController'         => 'src/Controllers/AuthController.php',
        'App\\Controllers\\DashboardController'    => 'src/Controllers/DashboardController.php',
        'App\\Controllers\\ContainerController'    => 'src/Controllers/ContainerController.php',
        'App\\Controllers\\ShipmentController'     => 'src/Controllers/ShipmentController.php',
        'App\\Controllers\\DeliveryPlannerController' => 'src/Controllers/DeliveryPlannerController.php',
        'App\\Controllers\\AnalysisController'     => 'src/Controllers/AnalysisController.php',
        'App\\Controllers\\AdminController'        => 'src/Controllers/AdminController.php',
        'App\\Controllers\\ApiController'          => 'src/Controllers/ApiController.php',
    ];

    if (isset($classMap[$class])) {
        $file = $baseDir . '/' . $classMap[$class];
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Fallback: try src/ namespace
    $relative = str_replace('\\', '/', substr($class, 4)); // strip 'App\' prefix
    $file = $baseDir . '/src/' . $relative . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
