<?php
/**
 * CN Track - Cron Background Tracker
 *
 * Usage: Add to your crontab to auto-refresh tracking every 5 minutes:
 *   */5 * * * * php /path/to/CNTrack/cron-track.php
 *
 * Or run manually:
 *   php cron-track.php
 *
 * This script logs in as the first active user and tracks all containers.
 * Output goes to data/cron.log.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Container;
use App\Models\User;
use App\Services\TrackingService;

$logFile = __DIR__ . '/data/cron.log';
$log = function (string $msg) use ($logFile) {
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
};

$log('--- Cron track started ---');

// Get first active user
$pdo = Database::getInstance()->getConnection();
$user = $pdo->query("SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();

if (!$user) {
    $log('No active user found. Aborting.');
    exit(1);
}

$userId = (int) $user['id'];
$containers = Container::findByUser($userId, false);

if (empty($containers)) {
    $log('No active containers to track.');
    exit(0);
}

$log('Tracking ' . count($containers) . ' container(s) for user #' . $userId);

$result = TrackingService::trackContainers($containers, $userId);

$log("Tracked: {$result['tracked']}");

if (!empty($result['errors'])) {
    $log('Errors: ' . implode(', ', $result['errors']));
}

$log('--- Cron track finished ---');
