<?php $pageTitle = 'Add Containers - ' . ($shipment['title'] ?: 'Shipment #' . $shipment['id']); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-boxes me-2 text-primary"></i><?php echo htmlspecialchars($shipment['title'] ?: 'Shipment #' . $shipment['id']); ?></h4><small class="text-muted">Add container numbers to this shipment</small></div>
    <div class="d-flex gap-2"><a href="/shipments/view?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-primary"><i class="fas fa-eye me-1"></i>View</a><a href="/shipments" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a></div>
</div>

<div class="card-modern p-3 mb-4">
    <div class="row g-2">
        <div class="col-md-3"><div class="detail-label">BOL</div><div class="detail-value font-mono small"><?php echo htmlspecialchars($shipment['bill_of_lading'] ?: '-'); ?></div></div>
        <div class="col-md-3"><div class="detail-label">PO #</div><div class="detail-value font-mono small"><?php echo htmlspecialchars($shipment['po_number'] ?: '-'); ?></div></div>
        <div class="col-md-3"><div class="detail-label">Customer</div><div class="detail-value small"><?php echo htmlspecialchars($shipment['customer_name'] ?: '-'); ?></div></div>
        <div class="col-md-3"><div class="detail-label">Destination</div><div class="detail-value small"><?php echo htmlspecialchars($shipment['destination'] ?: '-'); ?></div></div>
    </div>
</div>

<div class="card-modern p-4 mb-4">
    <form method="POST" action="/shipments/add?id=<?php echo $shipment['id']; ?>">
        <label class="form-label fw-bold small"><i class="fas fa-plus-circle me-1 text-primary"></i>Add Container(s)</label>
        <textarea name="containers" class="form-control font-mono" rows="3" placeholder="CNRU123456&#10;MRSU654321, TCLU111111" required></textarea>
        <div class="text-end mt-2"><button type="submit" class="btn btn-primary px-4"><i class="fas fa-plus me-1"></i>Add to Shipment</button></div>
    </form>
</div>

<div class="card-modern p-0">
    <div class="p-3 border-bottom"><h6 class="fw-bold mb-0"><i class="fas fa-box me-2 text-primary"></i>Containers (<?php echo count($containers); ?>)</h6></div>
    <?php if (!empty($containers)): ?>
    <div class="table-container rounded-0 border-0">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Container</th><th>Status</th><th>State</th><th>Last Event</th><th>Location</th><th>ETA</th><th>LFD</th><th>Customs</th><th style="width:100px;"></th></tr></thead>
            <tbody>
                <?php foreach ($containers as $c):
                    $snap = $containerTracking[$c['id']] ?? null;
                    $st = strtolower($snap['waybill_status'] ?? '');
                    $badgeClass = 'bg-soft-gray';
                    if ($st === 'executing') $badgeClass = 'bg-soft-green';
                    elseif ($st === 'planned') $badgeClass = 'bg-soft-blue';
                    elseif ($st === 'pending') $badgeClass = 'bg-soft-yellow';
                    $customsRaw = $snap['customs_status'] ?? '';
                    $cs = customsStatus($customsRaw);
                    $rowClass = $cs === 'hold' ? 'row-inbond' : ($cs === 'cleared' ? 'row-released' : '');
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td class="font-mono fw-bold"><a href="/container?id=<?php echo $c['id']; ?>"><?php echo containerFormatDisplay($c['container_number']); ?></a></td>
                    <td><span class="badge-soft <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($snap['waybill_status'] ?? 'N/A'); ?></span></td>
                    <td class="small"><?php echo formatLoadEmpty($snap['load_empty'] ?? ''); ?></td>
                    <td class="small text-truncate" style="max-width:130px;"><?php echo htmlspecialchars($snap['last_event'] ?? '-'); ?></td>
                    <td class="fw-bold small"><?php echo htmlspecialchars($snap['last_event_location'] ?? '-'); ?></td>
                    <td class="small fw-bold text-primary"><?php if (!empty($snap['eta_local'])): ?><?php echo date('M d H:i', strtotime($snap['eta_local'])); ?><?php if (isWeekend($snap['eta_local'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?><?php else: ?>-<?php endif; ?></td>
                    <td class="small"><?php if (!empty($snap['last_free_day'])): ?><?php echo date('M d', strtotime($snap['last_free_day'])); ?><?php else: ?>-<?php endif; ?></td>
                    <td class="small"><?php if ($cs === 'hold'): ?><span class="text-danger fw-bold"><i class="fas fa-lock me-1"></i>HOLD</span><?php elseif ($cs === 'cleared'): ?><span class="text-success"><i class="fas fa-check-circle me-1"></i>OK</span><?php else: ?><?php echo htmlspecialchars(mb_strimwidth($customsRaw, 0, 15, '...')); ?><?php endif; ?></td>
                    <td><div class="d-flex gap-1"><form method="POST" action="/track-single" class="d-inline track-single-form" data-id="<?php echo $c['id']; ?>"><input type="hidden" name="container_id" value="<?php echo $c['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-primary" title="Refresh"><i class="fas fa-sync-alt"></i></button></form><a href="/container?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" title="Details"><i class="fas fa-eye"></i></a></div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-4 text-center text-muted"><i class="fas fa-box-open fa-2x mb-2"></i><p class="mb-0">No containers yet. Add container numbers above.</p></div>
    <?php endif; ?>
</div>
