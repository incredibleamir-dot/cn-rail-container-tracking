<?php
/**
 * Debug System - Logging + On-Page Panel
 */

class Debug {

    private static $entries = [];
    private static $startTime;
    private static $queryCount = 0;
    private static $apiCalls = [];

    public static function init() {
        self::$startTime = microtime(true);
        self::log('SESSION', 'Page request started', [
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri'     => $_SERVER['REQUEST_URI'] ?? '/',
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }

    // ─── Logging Methods ───

    public static function log($category, $message, $data = null) {
        self::$entries[] = [
            'time'     => date('H:i:s'),
            'category' => strtoupper($category),
            'message'  => $message,
            'data'     => $data,
            'level'    => self::levelForCategory($category),
        ];
    }

    public static function logError($severity, $message, $file, $line) {
        $levelMap = [
            E_ERROR             => 'ERROR',
            E_WARNING           => 'WARNING',
            E_PARSE             => 'ERROR',
            E_NOTICE            => 'WARNING',
            E_CORE_ERROR        => 'ERROR',
            E_CORE_WARNING      => 'WARNING',
            E_COMPILE_ERROR     => 'ERROR',
            E_COMPILE_WARNING   => 'WARNING',
            E_USER_ERROR        => 'ERROR',
            E_USER_WARNING      => 'WARNING',
            E_USER_NOTICE       => 'WARNING',
            E_STRICT            => 'INFO',
            E_RECOVERABLE_ERROR => 'ERROR',
            E_DEPRECATED        => 'INFO',
            E_USER_DEPRECATED   => 'INFO',
        ];

        $cat = $levelMap[$severity] ?? 'ERROR';
        $severityName = 'Unknown';
        $constMap = [
            E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE', E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];
        $severityName = $constMap[$severity] ?? "Severity={$severity}";

        $shortFile = str_replace([APP_DIR . '/', APP_DIR . '\\'], '', $file);

        self::log($cat, "{$severityName}: {$message}", [
            'file' => $shortFile,
            'line' => $line,
        ]);
    }

    public static function logException(Throwable $e) {
        $shortFile = str_replace([APP_DIR . '/', APP_DIR . '\\'], '', $e->getFile());
        self::log('ERROR', $e->getMessage(), [
            'file'  => $shortFile,
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    public static function logApiCall($url, $method, $httpCode, $durationMs, $responseSize = 0) {
        $status = $httpCode >= 200 && $httpCode < 300 ? 'OK' : 'FAIL';
        $sizeStr = $responseSize > 1024 ? round($responseSize / 1024, 1) . 'KB' : $responseSize . 'B';

        self::$apiCalls[] = [
            'url'      => $url,
            'method'   => $method,
            'httpCode' => $httpCode,
            'duration' => $durationMs,
            'size'     => $sizeStr,
            'status'   => $status,
            'time'     => date('H:i:s'),
        ];

        self::log('API', "{$method} {$url} | {$httpCode} | {$durationMs}ms | {$sizeStr}");
    }

    public static function logQuery($sql, $durationMs, $rowCount = 0) {
        self::$queryCount++;
        self::log('SQL', $sql, [
            'duration' => $durationMs . 'ms',
            'rows'     => $rowCount,
            'query_num' => self::$queryCount,
        ]);
    }

    public static function logAction($action, $details = '') {
        $userId = $_SESSION['user_id'] ?? null;
        self::log('ACTION', $action, [
            'user_id' => $userId,
            'details' => $details,
        ]);
    }

    public static function logSession($event) {
        $userId = $_SESSION['user_id'] ?? null;
        $role   = $_SESSION['role'] ?? null;
        $name   = $_SESSION['name'] ?? null;
        self::log('SESSION', $event, [
            'user_id' => $userId,
            'role'    => $role,
            'name'    => $name,
        ]);
    }

    // ─── Retrieval ───

    public static function getEntries() {
        return self::$entries;
    }

    public static function getErrorCount() {
        return count(array_filter(self::$entries, fn($e) => $e['level'] === 'ERROR'));
    }

    public static function getWarningCount() {
        return count(array_filter(self::$entries, fn($e) => $e['level'] === 'WARNING'));
    }

    public static function getApiCalls() {
        return self::$apiCalls;
    }

    public static function getQueryCount() {
        return self::$queryCount;
    }

    public static function getPageLoadTime() {
        if (!self::$startTime) return 0;
        return round((microtime(true) - self::$startTime) * 1000);
    }

    public static function hasErrors() {
        return self::getErrorCount() > 0;
    }

    public static function hasWarnings() {
        return self::getWarningCount() > 0;
    }

    // ─── File Logging ───

    public static function writeToFile() {
        if (empty(self::$entries)) return;

        $logFile = DEBUG_LOG_PATH;
        $lines = [];

        foreach (self::$entries as $entry) {
            $line = '[' . date('Y-m-d H:i:s') . '] ';
            $line .= '[' . $entry['category'] . '] ';
            $line .= $entry['message'];
            if (!empty($entry['data']) && is_array($entry['data'])) {
                $line .= ' | ' . json_encode($entry['data'], JSON_UNESCAPED_SLASHES);
            }
            $lines[] = $line . "\n";
        }

        @file_put_contents($logFile, implode('', $lines), FILE_APPEND | LOCK_EX);
    }

    // ─── On-Page Rendering ───

    public static function renderBanner() {
        if (!DEBUG_MODE) return;
        if (!self::hasErrors() && !self::hasWarnings()) return;

        $errorCount   = self::getErrorCount();
        $warningCount = self::getWarningCount();
        $errors = array_filter(self::$entries, fn($e) => in_array($e['level'], ['ERROR', 'WARNING']));
        $items = '';

        foreach ($errors as $e) {
            $color = $e['level'] === 'ERROR' ? '#dc3545' : '#ffc107';
            $file  = $e['data']['file'] ?? '';
            $line  = $e['data']['line'] ?? '';
            $loc   = $file ? " in {$file}:{$line}" : '';
            $items .= '<div style="padding:8px 16px;border-bottom:1px solid rgba(255,255,255,0.1);font-size:13px;font-family:monospace;">';
            $items .= '<span style="color:' . $color . ';font-weight:700;">[' . $e['level'] . ']</span> ';
            $items .= htmlspecialchars($e['message']);
            if ($loc) $items .= '<span style="opacity:0.6;margin-left:8px;">' . htmlspecialchars($loc) . '</span>';
            $items .= '</div>';
        }

        $total = $errorCount + $warningCount;
        echo '<div id="debug-error-banner" style="background:#1a1a2e;color:#fff;position:fixed;top:0;left:0;right:0;z-index:99999;font-family:monospace;box-shadow:0 4px 12px rgba(0,0,0,0.4);">';
        echo '<div onclick="document.getElementById(\'debug-error-list\').style.display=document.getElementById(\'debug-error-list\').style.display===\'none\'?\'block\':\'none\';" style="padding:10px 20px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">';
        echo '<span><span style="color:#ffc107;">' . $warningCount . ' Warning(s)</span> &nbsp; <span style="color:#dc3545;">' . $errorCount . ' Error(s)</span></span>';
        echo '<span style="opacity:0.6;">Click to expand/collapse</span>';
        echo '</div>';
        echo '<div id="debug-error-list" style="display:none;max-height:300px;overflow-y:auto;">' . $items . '</div>';
        echo '</div>';
    }

    public static function renderPanel() {
        if (!DEBUG_MODE) return;

        $errors       = self::getErrorCount();
        $warnings     = self::getWarningCount();
        $apiCalls     = self::$apiCalls;
        $queryCount   = self::$queryCount;
        $pageMs       = self::getPageLoadTime();
        $userId       = $_SESSION['user_id'] ?? 'none';
        $role         = $_SESSION['role'] ?? 'none';
        $name         = $_SESSION['name'] ?? '';

        $apiHtml = '';
        foreach ($apiCalls as $api) {
            $icon = $api['httpCode'] >= 200 && $api['httpCode'] < 300 ? '&#10003;' : '&#10007;';
            $color = $api['httpCode'] >= 200 && $api['httpCode'] < 300 ? '#28a745' : '#dc3545';
            $apiHtml .= '<div style="padding:4px 0;border-bottom:1px solid #333;font-size:12px;">';
            $apiHtml .= '<span style="color:' . $color . ';">' . $icon . '</span> ';
            $apiHtml .= '<span style="color:#adb5bd;">' . htmlspecialchars($api['method']) . '</span> ';
            $apiHtml .= '<span style="color:#fff;">' . htmlspecialchars($api['url']) . '</span>';
            $apiHtml .= ' <span style="color:#6c757d;">' . $api['httpCode'] . ' | ' . $api['duration'] . 'ms | ' . $api['size'] . '</span>';
            $apiHtml .= '</div>';
        }

        $actionEntries = array_filter(self::$entries, fn($e) => $e['category'] === 'ACTION');
        $actionHtml = '';
        foreach ($actionEntries as $a) {
            $actionHtml .= '<div style="padding:3px 0;font-size:12px;border-bottom:1px solid #333;">';
            $actionHtml .= '<span style="color:#6c757d;">' . $a['time'] . '</span> ';
            $actionHtml .= '<span style="color:#17a2b8;">' . htmlspecialchars($a['message']) . '</span>';
            if (!empty($a['data']['details'])) {
                $actionHtml .= ' <span style="color:#adb5bd;">' . htmlspecialchars($a['data']['details']) . '</span>';
            }
            $actionHtml .= '</div>';
        }

        $errorEntries = array_filter(self::$entries, fn($e) => $e['level'] === 'ERROR' || $e['level'] === 'WARNING');
        $errorHtml = '';
        foreach ($errorEntries as $e) {
            $color = $e['level'] === 'ERROR' ? '#dc3545' : '#ffc107';
            $file = $e['data']['file'] ?? '';
            $line = $e['data']['line'] ?? '';
            $errorHtml .= '<div style="padding:3px 0;font-size:11px;border-bottom:1px solid #333;font-family:monospace;">';
            $errorHtml .= '<span style="color:' . $color . ';">[' . $e['level'] . ']</span> ';
            $errorHtml .= htmlspecialchars($e['message']);
            if ($file) $errorHtml .= ' <span style="color:#6c757d;">' . htmlspecialchars($file) . ':' . $line . '</span>';
            $errorHtml .= '</div>';
        }

        $total = $errors + $warnings;
        echo '<div id="debug-panel">';
        echo '<div id="debug-toggle" onclick="document.getElementById(\'debug-content\').style.display=document.getElementById(\'debug-content\').style.display===\'none\'?\'block\':\'none\';">';
        echo '<span style="color:#ffc107;">' . $warnings . '&#9888;</span> ';
        echo '<span style="color:#dc3545;">' . $errors . '&#10007;</span> ';
        echo '<span style="color:#28a745;">⏱ ' . $pageMs . 'ms</span>';
        echo '</div>';
        echo '<div id="debug-content" style="display:none;">';

        echo '<div style="padding:8px 12px;border-bottom:1px solid #333;font-size:12px;">';
        echo '<strong style="color:#28a745;">SESSION:</strong> ';
        echo '<span style="color:#fff;">user_id=' . htmlspecialchars($userId) . ' role=' . htmlspecialchars($role);
        if ($name) echo ' name=' . htmlspecialchars($name);
        echo '</span></div>';

        echo '<div style="padding:8px 12px;border-bottom:1px solid #333;font-size:12px;">';
        echo '<strong style="color:#28a745;">PERF:</strong> ';
        echo '<span style="color:#fff;">Page: ' . $pageMs . 'ms | Queries: ' . $queryCount . ' | API calls: ' . count($apiCalls) . '</span>';
        echo '</div>';

        if ($apiCalls) {
            echo '<div style="padding:8px 12px;border-bottom:1px solid #333;">';
            echo '<strong style="color:#17a2b8;font-size:12px;">API CALLS (' . count($apiCalls) . ')</strong>';
            echo '<div style="max-height:150px;overflow-y:auto;">' . $apiHtml . '</div></div>';
        }

        if ($queryCount > 0) {
            echo '<div style="padding:8px 12px;border-bottom:1px solid #333;font-size:12px;">';
            echo '<strong style="color:#6f42c1;">SQL QUERIES (' . $queryCount . ')</strong></div>';
        }

        if ($actionEntries) {
            echo '<div style="padding:8px 12px;border-bottom:1px solid #333;">';
            echo '<strong style="color:#17a2b8;font-size:12px;">USER ACTIONS</strong>';
            echo '<div style="max-height:120px;overflow-y:auto;">' . $actionHtml . '</div></div>';
        }

        if ($errorEntries) {
            echo '<div style="padding:8px 12px;border-bottom:1px solid #333;">';
            echo '<strong style="color:#dc3545;font-size:12px;">ERRORS & WARNINGS (' . $total . ')</strong>';
            echo '<div style="max-height:150px;overflow-y:auto;">' . $errorHtml . '</div></div>';
        }

        echo '<div style="padding:6px 12px;text-align:center;">';
        echo '<button onclick="document.getElementById(\'debug-content\').style.display=\'none\';" style="background:#333;color:#fff;border:none;padding:4px 12px;border-radius:4px;font-size:11px;cursor:pointer;margin:2px;">Close</button>';
        echo '<button onclick="navigator.clipboard.writeText(document.getElementById(\'debug-content\').innerText);" style="background:#333;color:#fff;border:none;padding:4px 12px;border-radius:4px;font-size:11px;cursor:pointer;margin:2px;">Copy</button>';
        echo '</div>';

        echo '</div></div>';
    }

    // ─── Private Helpers ───

    private static function levelForCategory($cat) {
        $map = [
            'ERROR'   => 'ERROR',
            'WARNING' => 'WARNING',
            'SQL'     => 'INFO',
            'API'     => 'INFO',
            'ACTION'  => 'INFO',
            'SESSION' => 'INFO',
            'PERF'    => 'INFO',
            'INFO'    => 'INFO',
        ];
        return $map[strtoupper($cat)] ?? 'INFO';
    }
}
