<?php $pageTitle = 'Analysis'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
.stat-card { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e9ecef; height: 100%; }
.stat-card .stat-number { font-size: 1.8rem; font-weight: 700; line-height: 1; }
.stat-card .stat-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-top: 0.25rem; }
.chart-card { background: #fff; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e9ecef; overflow: hidden; }
.chart-card h6 { font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; }
.chart-wrap { position: relative; width: 100%; overflow: hidden; }
.chart-wrap-sm { height: 160px; }
.chart-wrap-bar { height: 200px; }
.chart-wrap-lg { height: 120px; }
.alert-row { padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.8rem; }
.alert-row:last-child { border-bottom: none; }
.alert-row .fw-mono { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
.lfd-past { color: #dc3545; }
.lfd-today { color: #fd7e14; }
.lfd-soon { color: #ffc107; }
.eta-row { padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.8rem; }
.eta-row:last-child { border-bottom: none; }
.progress-soft { height: 8px; border-radius: 4px; background: #e9ecef; }
.progress-soft .bar { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Analysis</h4><small class="text-muted">Overview of your container tracking data.</small></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-number text-primary"><?php echo $totalContainers; ?></div><div class="stat-label">Total Containers</div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-number text-success"><?php echo $activeContainers; ?></div><div class="stat-label">Active</div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-number text-secondary"><?php echo $archivedContainers; ?></div><div class="stat-label">Archived</div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-number text-info"><?php echo $totalShipments; ?></div><div class="stat-label">Shipments</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-signal me-2 text-primary"></i>Waybill Status</h6><div class="chart-wrap chart-wrap-sm"><canvas id="chartStatus"></canvas></div></div></div>
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-passport me-2 text-primary"></i>Customs Status</h6><div class="chart-wrap chart-wrap-sm"><canvas id="chartCustoms"></canvas></div></div></div>
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-box me-2 text-primary"></i>Load State</h6><div class="chart-wrap chart-wrap-sm"><canvas id="chartLoad"></canvas></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-users me-2 text-primary"></i>Top Customers</h6><div class="chart-wrap chart-wrap-bar"><canvas id="chartCustomers"></canvas></div></div></div>
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-map-marker-alt me-2 text-primary"></i>Top Destinations</h6><div class="chart-wrap chart-wrap-bar"><canvas id="chartDestinations"></canvas></div></div></div>
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-location-dot me-2 text-primary"></i>Top Locations</h6><div class="chart-wrap chart-wrap-bar"><canvas id="chartLocations"></canvas></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-ship me-2 text-primary"></i>Containers by Shipment</h6><div class="chart-wrap chart-wrap-bar"><canvas id="chartShipments"></canvas></div></div></div>
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-calendar-check me-2 text-primary"></i>Delivery Planning</h6>
        <div class="text-center mt-3">
            <div class="stat-number text-success" style="font-size:2rem;"><?php echo $deliveryStats['planned']; ?></div>
            <div class="text-muted small mb-2">planned of <?php echo $deliveryStats['total']; ?> active</div>
            <div class="progress-soft mx-auto" style="max-width:200px;"><div class="bar bg-success" style="width:<?php echo $deliveryStats['total'] > 0 ? round(($deliveryStats['planned'] / $deliveryStats['total']) * 100) : 0; ?>%"></div></div>
            <div class="mt-2 small"><span class="text-success fw-bold"><?php echo $deliveryStats['planned']; ?> planned</span> &middot; <span class="text-warning fw-bold"><?php echo $deliveryStats['unplanned']; ?> unplanned</span></div>
        </div>
    </div></div>
    <div class="col-md-4"><div class="chart-card"><h6><i class="fas fa-exclamation-triangle me-2 text-danger"></i>LFD Alerts</h6>
        <?php if (empty($lfdAlerts)): ?>
            <div class="text-center text-muted py-3">No upcoming LFD alerts.</div>
        <?php else: ?>
            <?php foreach ($lfdAlerts as $alert): $lfd = $alert['last_free_day']; $today = date('Y-m-d'); $diff = strtotime($lfd) - strtotime($today); $daysLeft = floor($diff / 86400); $cls = $daysLeft < 0 ? 'lfd-past' : ($daysLeft === 0 ? 'lfd-today' : 'lfd-soon'); ?>
            <div class="alert-row">
                <span class="fw-mono"><?php echo htmlspecialchars($alert['container_number']); ?></span>
                <span class="text-muted mx-1">&middot;</span>
                <span class="<?php echo $cls; ?> fw-bold"><?php echo date('M d', strtotime($lfd)); ?></span>
                <span class="text-muted"><?php echo $daysLeft < 0 ? abs($daysLeft) . 'd overdue' : ($daysLeft === 0 ? 'Today' : $daysLeft . 'd left'); ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="chart-card"><h6><i class="fas fa-clock me-2 text-primary"></i>Upcoming ETAs (Next 7 Days)</h6>
        <?php if (empty($upcomingEtas)): ?>
            <div class="text-center text-muted py-3">No ETAs in the next 7 days.</div>
        <?php else: ?>
            <?php foreach ($upcomingEtas as $eta): $etaDate = $eta['eta_local']; $today = date('Y-m-d'); $diff = strtotime($etaDate) - strtotime($today); $daysLeft = floor($diff / 86400); $etaColor = $daysLeft <= 1 ? 'text-danger' : ($daysLeft <= 3 ? 'text-warning' : 'text-primary'); ?>
            <div class="eta-row">
                <span class="fw-mono fw-bold"><?php echo htmlspecialchars($eta['container_number']); ?></span>
                <span class="text-muted mx-1">&middot;</span>
                <span class="<?php echo $etaColor; ?> fw-bold"><?php echo date('M d H:i', strtotime($etaDate)); ?></span>
                <span class="text-muted">&middot;</span>
                <span class="small"><?php echo htmlspecialchars($eta['eta_station'] ?: '-'); ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12"><div class="chart-card"><h6><i class="fas fa-chart-line me-2 text-primary"></i>Tracking Activity (Last 30 Days)</h6><div class="chart-wrap chart-wrap-lg"><canvas id="chartActivity"></canvas></div></div></div>
</div>

<script>
var COLORS = ['#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0','#6f42c1','#fd7e14','#20c997','#d63384','#6c757d'];
function makeDoughnut(canvasId, data, colors) {
    var labels = Object.keys(data); var values = Object.values(data);
    if (labels.length === 0) { labels = ['(none)']; values = [1]; }
    new Chart(document.getElementById(canvasId), { type: 'doughnut', data: { labels: labels, datasets: [{ data: values, backgroundColor: colors || COLORS, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8 } } } } });
}
function makeBar(canvasId, data, color) {
    var labels = Object.keys(data); var values = Object.values(data);
    if (labels.length === 0) return;
    new Chart(document.getElementById(canvasId), { type: 'bar', data: { labels: labels, datasets: [{ label: 'Count', data: values, backgroundColor: color || '#0d6efd', borderRadius: 4 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { font: { size: 10 } } }, y: { ticks: { font: { size: 10 } } } } } });
}
function makeLine(canvasId, labels, values) {
    if (labels.length === 0) return;
    new Chart(document.getElementById(canvasId), { type: 'line', data: { labels: labels, datasets: [{ label: 'Snapshots', data: values, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3, pointRadius: 2 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { size: 9 }, maxRotation: 45 } }, y: { beginAtZero: true, ticks: { font: { size: 10 } } } } } });
}
makeDoughnut('chartStatus', <?php echo json_encode($statusData); ?>, ['#198754','#0d6efd','#ffc107','#dc3545','#0dcaf0','#6f42c1','#6c757d','#fd7e14']);
makeDoughnut('chartCustoms', <?php echo json_encode($customsData); ?>, ['#198754','#dc3545','#ffc107','#0dcaf0','#6c757d']);
makeDoughnut('chartLoad', <?php echo json_encode($loadData); ?>, ['#0d6efd','#dc3545','#6c757d']);
makeBar('chartCustomers', <?php echo json_encode($topCustomers); ?>, '#0d6efd');
makeBar('chartDestinations', <?php echo json_encode($topDestinations); ?>, '#198754');
makeBar('chartLocations', <?php echo json_encode($topLocations); ?>, '#6f42c1');
makeBar('chartShipments', <?php echo json_encode($byShipment); ?>, '#fd7e14');
var activityData = <?php echo json_encode($trackingActivity); ?>;
makeLine('chartActivity', Object.keys(activityData), Object.values(activityData));
</script>
