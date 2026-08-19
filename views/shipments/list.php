<?php $pageTitle = 'Shipments'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-ship me-2 text-primary"></i>Shipments</h4>
        <small class="text-muted"><?php echo count($shipments); ?> shipment(s)</small>
    </div>
    <div class="d-flex gap-2">
        <a href="/shipments/create" class="btn btn-primary px-4" style="border-radius:10px;"><i class="fas fa-plus me-1"></i>New Shipment</a>
        <?php if ($showArchived): ?>
            <a href="/shipments" class="btn btn-outline-secondary">Active</a>
        <?php else: ?>
            <a href="/shipments?archived=1" class="btn btn-outline-warning"><i class="fas fa-archive me-1"></i>Archived</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($shipments)): ?>
<div class="card-modern p-0">
    <div class="table-container rounded-0 border-0">
        <table class="table mb-0" id="shipmentsTable">
            <thead class="table-light">
                <tr><th>Title</th><th>BOL</th><th>PO #</th><th>Customer</th><th>Destination</th><th>Containers</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($shipments as $s): ?>
                <tr>
                    <td class="fw-bold"><a href="/shipments/view?id=<?php echo $s['id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($s['title'] ?: 'Shipment #' . $s['id']); ?></a></td>
                    <td class="small font-mono"><?php echo htmlspecialchars($s['bill_of_lading'] ?: '-'); ?></td>
                    <td class="small font-mono"><?php echo htmlspecialchars($s['po_number'] ?: '-'); ?></td>
                    <td class="small"><?php echo htmlspecialchars($s['customer_name'] ?: '-'); ?></td>
                    <td class="small"><?php echo htmlspecialchars($s['destination'] ?: '-'); ?></td>
                    <td><span class="badge bg-primary"><?php echo (int)$s['container_count']; ?></span></td>
                    <td class="small"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/shipments/view?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="/shipments/add?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-success" title="Add Containers"><i class="fas fa-plus"></i></a>
                            <?php if ($showArchived): ?>
                            <form method="POST" action="/shipments/unarchive" class="d-inline"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-success" title="Restore"><i class="fas fa-undo"></i></button></form>
                            <?php else: ?>
                            <form method="POST" action="/shipments/archive" class="d-inline"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-warning" title="Archive"><i class="fas fa-archive"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="text-center py-5">
    <i class="fas fa-ship fa-3x text-muted opacity-25 mb-3"></i>
    <h5 class="text-muted">No shipments yet</h5>
    <p class="text-muted">Create your first shipment to group containers together.</p>
    <a href="/shipments/create" class="btn btn-primary px-4"><i class="fas fa-plus me-1"></i>Create Shipment</a>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn.DataTable && document.getElementById('shipmentsTable')) {
        $('#shipmentsTable').DataTable({
            paging: true, pageLength: 25, searching: true, info: true,
            order: [[6, 'desc']], stateSave: true,
            language: { search: "", searchPlaceholder: "Filter shipments..." }
        });
    }
});
</script>
