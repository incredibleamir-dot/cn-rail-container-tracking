<?php $pageTitle = 'Container List'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-list me-2 text-primary"></i>Container List</h4>
        <small class="text-muted"><?php echo count($allContainers); ?> total containers</small>
    </div>
    <a href="/" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="mb-3">
    <span class="text-muted small me-2 fw-bold"><i class="fas fa-layer-group me-1"></i>Group by:</span>
    <a href="/containers" class="badge <?php echo empty($groupField) ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">None</a>
    <a href="/containers?group=bill_of_lading" class="badge <?php echo $groupField === 'bill_of_lading' ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">BOL</a>
    <a href="/containers?group=po_number" class="badge <?php echo $groupField === 'po_number' ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">PO #</a>
    <a href="/containers?group=customer_name" class="badge <?php echo $groupField === 'customer_name' ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">Customer</a>
    <a href="/containers?group=destination" class="badge <?php echo $groupField === 'destination' ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">Destination</a>
    <a href="/containers?group=commodity" class="badge <?php echo $groupField === 'commodity' ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">Commodity</a>
</div>

<?php if ($groupField && !empty($groupedContainers)): ?>
    <?php foreach ($groupedContainers as $groupName => $groupContainers): ?>
    <div class="card-modern p-0 mb-4">
        <div class="p-3 border-bottom bg-light">
            <h6 class="fw-bold mb-0"><i class="fas fa-folder-open me-2 text-primary"></i><?php echo htmlspecialchars($groupName); ?> <span class="badge bg-secondary ms-2"><?php echo count($groupContainers); ?></span></h6>
        </div>
        <?php renderContainerTable($groupContainers, $snapshots); ?>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card-modern p-0">
        <?php renderContainerTable($allContainers, $snapshots); ?>
    </div>
<?php endif; ?>

<?php
function renderContainerTable($containers, $snapshots) {
?>
    <div class="table-container">
        <table class="table w-100 mb-0 container-list-table">
            <thead>
                <tr><th>Container</th><th>Status</th><th>State</th><th>Last Event</th><th>Location</th><th>ETA</th><th>LFD</th><th>BOL</th><th>PO #</th><th>Customer</th><th>Destination</th><th>Tags</th><th>Archived</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($containers as $c):
                    $latest = $snapshots[$c['id']] ?? null;
                    $st = strtolower($latest['waybill_status'] ?? '');
                    $badgeClass = 'bg-soft-gray';
                    if ($st === 'executing') $badgeClass = 'bg-soft-green';
                    elseif ($st === 'planned') $badgeClass = 'bg-soft-blue';
                    elseif ($st === 'pending') $badgeClass = 'bg-soft-yellow';
                ?>
                <tr>
                    <td class="font-mono fw-bold"><a href="/container?id=<?php echo $c['id']; ?>" class="container-link text-decoration-none"><?php echo containerFormatDisplay($c['container_number']); ?></a></td>
                    <td><span class="badge-soft <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($latest['waybill_status'] ?? 'N/A'); ?></span></td>
                    <td class="small"><?php echo formatLoadEmpty($latest['load_empty'] ?? ''); ?></td>
                    <td class="small text-truncate" style="max-width:120px;"><?php echo htmlspecialchars($latest['last_event'] ?? '-'); ?></td>
                    <td class="fw-bold small"><?php echo htmlspecialchars($latest['last_event_location'] ?? '-'); ?></td>
                    <td class="small"><?php if (!empty($latest['eta_local'])): ?><?php echo date('M d', strtotime($latest['eta_local'])); ?><?php else: ?>-<?php endif; ?></td>
                    <td class="small"><?php if (!empty($latest['last_free_day'])): ?><?php echo date('M d', strtotime($latest['last_free_day'])); ?><?php else: ?>-<?php endif; ?></td>
                    <td class="small font-mono"><?php echo htmlspecialchars($c['bill_of_lading'] ?: '-'); ?></td>
                    <td class="small font-mono"><?php echo htmlspecialchars($c['po_number'] ?: '-'); ?></td>
                    <td class="small"><?php echo htmlspecialchars($c['customer_name'] ?: '-'); ?></td>
                    <td class="small"><?php echo htmlspecialchars($c['destination'] ?: '-'); ?></td>
                    <td><?php foreach (parseTagsString($c['tags']) as $t): ?><span class="badge bg-light text-dark border tag-pill"><?php echo htmlspecialchars($t); ?></span><?php endforeach; ?></td>
                    <td class="text-center"><?php if ($c['is_archived']): ?><span class="badge bg-warning text-dark"><i class="fas fa-archive"></i></span><?php endif; ?></td>
                    <td><a href="/container?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" title="Details"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn.DataTable && document.querySelector('.container-list-table')) {
        $('.container-list-table').DataTable({
            paging: true, pageLength: 25, searching: true, info: true,
            order: [[1, 'asc']], stateSave: true, colReorder: true,
            language: { search: "", searchPlaceholder: "Filter containers..." }
        });
    }
});
</script>
