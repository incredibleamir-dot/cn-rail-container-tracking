<?php
/**
 * CN Track - Debug Log Analyzer
 * Standalone file - place in root and access via browser
 */

$logFile = __DIR__ . '/data/debug.log';

if (!file_exists($logFile)) {
    echo '<div class="alert alert-warning">No debug log found at data/debug.log</div>';
    exit;
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$total = count($lines);

$stats = [
    'total' => $total,
    'sessions' => 0,
    'sql' => 0,
    'api' => 0,
    'actions' => 0,
    'warnings' => 0,
    'errors' => 0,
    'ips' => [],
    'api_calls' => [],
    'slow_queries' => [],
    'recent' => [],
];

$entries = [];
foreach ($lines as $i => $line) {
    preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[(\w+)\] (.+)/', $line, $m);
    if (!$m) continue;

    $entry = [
        'time' => $m[1],
        'type' => $m[2],
        'message' => $m[3],
        'line_num' => $i + 1,
    ];

    // Parse JSON payload
    $payload = substr($line, strpos($line, ' | ') + 3);
    $data = json_decode($payload, true);
    $entry['data'] = $data;

    $entries[] = $entry;

    switch ($m[2]) {
        case 'SESSION':
            if (strpos($m[3], 'LOGIN') !== false && strpos($m[3], 'Page request') === false) {
                $stats['sessions']++;
            }
            if (isset($data['ip'])) {
                $stats['ips'][$data['ip']] = ($stats['ips'][$data['ip']] ?? 0) + 1;
            }
            break;
        case 'SQL':
            $stats['sql']++;
            $dur = (int)str_replace('ms', '', $data['duration'] ?? '0');
            if ($dur > 50) {
                $stats['slow_queries'][] = ['time' => $m[1], 'query' => $m[3], 'duration' => $data['duration']];
            }
            break;
        case 'API':
            $stats['api']++;
            if (preg_match('/(GET|POST) (https?:\/\/[^\s]+) \| (\d+)/', $m[3], $am)) {
                $stats['api_calls'][] = [
                    'time' => $m[1],
                    'method' => $am[1],
                    'url' => $am[2],
                    'status' => $am[3],
                    'duration' => $data['duration'] ?? '-',
                    'size' => $data['size'] ?? '-',
                ];
            }
            break;
        case 'ACTION':
            $stats['actions']++;
            break;
        case 'WARNING':
            $stats['warnings']++;
            break;
        case 'ERROR':
            $stats['errors']++;
            break;
    }
}

// Recent 50 entries
$stats['recent'] = array_slice(array_reverse($entries), 0, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CN Track - Log Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-num { font-size: 1.8rem; font-weight: 700; }
        .log-line { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; padding: 4px 8px; border-bottom: 1px solid #f1f3f5; }
        .log-line:hover { background: #f8f9fa; }
        .type-SESSION { color: #0d6efd; }
        .type-SQL { color: #198754; }
        .type-API { color: #6f42c1; }
        .type-ACTION { color: #fd7e14; }
        .type-WARNING { color: #ffc107; }
        .type-ERROR { color: #dc3545; }
        .type-INFO { color: #6c757d; }
        .tab-content { background: white; border-radius: 0 0 12px 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .api-card { font-size: 0.82rem; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-4">

    <h4 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2 text-primary"></i>Debug Log Analyzer</h4>
    <small class="text-muted mb-4 d-block"><?php echo $total; ?> total entries | <?php echo count($stats['ips']); ?> unique IPs | File: data/debug.log</small>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 text-center">
                <div class="stat-num text-primary"><?php echo $stats['sessions']; ?></div>
                <div class="text-muted small fw-bold">Logins</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 text-center">
                <div class="stat-num text-success"><?php echo $stats['sql']; ?></div>
                <div class="text-muted small fw-bold">SQL Queries</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 text-center">
                <div class="stat-num" style="color:#6f42c1;"><?php echo $stats['api']; ?></div>
                <div class="text-muted small fw-bold">API Calls</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 text-center">
                <div class="stat-num text-danger"><?php echo $stats['warnings'] + $stats['errors']; ?></div>
                <div class="text-muted small fw-bold">Warnings/Errors</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-recent">Recent</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-api">API Calls</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ips">IPs</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-slow">Slow Queries</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-warnings">Warnings</a></li>
    </ul>
    <div class="tab-content p-3 mb-4">

        <!-- Recent -->
        <div class="tab-pane fade show active" id="tab-recent">
            <?php foreach ($stats['recent'] as $e): ?>
                <div class="log-line">
                    <span class="text-muted"><?php echo $e['time']; ?></span>
                    <span class="type-<?php echo $e['type']; ?> fw-bold"><?php echo str_pad($e['type'], 8); ?></span>
                    <span><?php echo htmlspecialchars($e['message']); ?></span>
                    <?php if ($e['data']): ?>
                        <span class="text-muted"> | <?php echo htmlspecialchars(json_encode($e['data'], JSON_UNESCAPED_SLASHES)); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- API Calls -->
        <div class="tab-pane fade" id="tab-api">
            <?php if (empty($stats['api_calls'])): ?>
                <p class="text-muted">No API calls logged.</p>
            <?php else: ?>
                <table class="table table-sm api-card">
                    <thead><tr><th>Time</th><th>Method</th><th>URL</th><th>Status</th><th>Duration</th><th>Size</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['api_calls'] as $api): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $api['time']; ?></td>
                            <td><span class="badge bg-<?php echo $api['method'] === 'POST' ? 'primary' : 'success'; ?>"><?php echo $api['method']; ?></span></td>
                            <td class="font-monospace small"><?php echo htmlspecialchars($api['url']); ?></td>
                            <td><span class="badge bg-<?php echo $api['status'] == 200 ? 'success' : 'danger'; ?>"><?php echo $api['status']; ?></span></td>
                            <td><?php echo htmlspecialchars($api['duration']); ?></td>
                            <td><?php echo htmlspecialchars($api['size']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- IPs -->
        <div class="tab-pane fade" id="tab-ips">
            <table class="table table-sm">
                <thead><tr><th>IP Address</th><th>Requests</th></tr></thead>
                <tbody>
                <?php foreach ($stats['ips'] as $ip => $count): ?>
                    <tr>
                        <td class="font-monospace"><?php echo $ip; ?></td>
                        <td><span class="badge bg-secondary"><?php echo $count; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Slow Queries -->
        <div class="tab-pane fade" id="tab-slow">
            <?php if (empty($stats['slow_queries'])): ?>
                <p class="text-muted">No slow queries (>50ms).</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>Time</th><th>Query</th><th>Duration</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['slow_queries'] as $sq): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $sq['time']; ?></td>
                            <td class="font-monospace small"><?php echo htmlspecialchars($sq['query']); ?></td>
                            <td class="text-warning fw-bold"><?php echo $sq['duration']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Warnings -->
        <div class="tab-pane fade" id="tab-warnings">
            <?php
            $warns = array_filter($entries, function($e) { return $e['type'] === 'WARNING' || $e['type'] === 'ERROR'; });
            if (empty($warns)): ?>
                <p class="text-muted">No warnings or errors.</p>
            <?php else: ?>
                <?php foreach ($warns as $w): ?>
                    <div class="log-line <?php echo $w['type'] === 'ERROR' ? 'text-danger' : 'text-warning'; ?>">
                        <span class="text-muted"><?php echo $w['time']; ?></span>
                        <span class="fw-bold">[<?php echo $w['type']; ?>]</span>
                        <span class="small"><?php echo htmlspecialchars($w['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
