<?php
/**
 * CN Track - Bootstrap
 * Session, PDO, error handlers, helpers, auth guard
 */

$__CNTRACK_START = microtime(true);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/Debug.php';
require_once __DIR__ . '/helpers/container_utils.php';
require_once __DIR__ . '/helpers/cn_api.php';
if (file_exists(__DIR__ . '/helpers/google_sheet.php')) {
    require_once __DIR__ . '/helpers/google_sheet.php';
}

date_default_timezone_set(TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Debug init
Debug::init();

set_error_handler(function($severity, $message, $file, $line) {
    Debug::logError($severity, $message, $file, $line);
    return true;
});

set_exception_handler(function($exception) {
    Debug::logException($exception);
    if (DEBUG_MODE) {
        http_response_code(500);
        echo '<h1>Application Error</h1>';
        echo '<pre>' . htmlspecialchars($exception->getMessage()) . "\n";
        echo $exception->getFile() . ':' . $exception->getLine() . '</pre>';
        Debug::renderPanel();
        exit;
    }
    http_response_code(500);
    echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
    exit;
});

register_shutdown_function(function() {
    Debug::writeToFile();
});

// Database
$dataDir = dirname(DB_PATH);
if (!is_dir($dataDir)) {
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    mkdir($dataDir, 0777, true);
}

if (!file_exists(DB_PATH)) {
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
} catch (PDOException $e) {
    Debug::log('ERROR', 'Database connection failed: ' . $e->getMessage());
    if (DEBUG_MODE) {
        echo '<h1>Database Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    } else {
        echo '<h1>Database Error</h1><p>Could not connect to database.</p>';
    }
    exit;
}

// Initialize Database singleton
App\Core\Database::init($pdo);

// Schema migrations (run once)
$pdo->exec("CREATE TABLE IF NOT EXISTS shipments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT DEFAULT '',
    bill_of_lading TEXT DEFAULT '',
    po_number TEXT DEFAULT '',
    customer_name TEXT DEFAULT '',
    destination TEXT DEFAULT '',
    commodity TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    tags TEXT DEFAULT '',
    is_archived INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS delivery_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    container_id INTEGER NOT NULL,
    shipment_id INTEGER DEFAULT NULL,
    delivery_date TEXT,
    delivery_time TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    UNIQUE(user_id, container_id)
)");

// Migration: add shipment_id to containers if missing
try {
    $colCheck = $pdo->query("PRAGMA table_info(containers)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCol = false;
    foreach ($colCheck as $col) { if ($col['name'] === 'shipment_id') { $hasCol = true; break; } }
    if (!$hasCol) {
        $pdo->exec("ALTER TABLE containers ADD COLUMN shipment_id INTEGER DEFAULT NULL REFERENCES shipments(id) ON DELETE SET NULL");
    }
} catch (Exception $e) {}

// Migration: add customs_timestamp if missing
try {
    $colCheck = $pdo->query("PRAGMA table_info(tracking_snapshots)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCol = false;
    foreach ($colCheck as $col) { if ($col['name'] === 'customs_timestamp') { $hasCol = true; break; } }
    if (!$hasCol) {
        $pdo->exec("ALTER TABLE tracking_snapshots ADD COLUMN customs_timestamp TEXT");
    }
} catch (Exception $e) {}

// Migration: add detention columns to delivery_plans if missing
$detentionCols = ['free_days' => 'INTEGER DEFAULT 0', 'use_working_days' => 'INTEGER DEFAULT 1', 'day_of_interchange' => 'INTEGER DEFAULT 0', 'detention_days' => 'INTEGER DEFAULT 0'];
try {
    $colCheck = $pdo->query("PRAGMA table_info(delivery_plans)")->fetchAll(PDO::FETCH_ASSOC);
    $existingCols = array_column($colCheck, 'name');
    foreach ($detentionCols as $col => $type) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec("ALTER TABLE delivery_plans ADD COLUMN {$col} {$type}");
        }
    }
} catch (Exception $e) {}

// Session
session_start();
Debug::logSession('Page request');
