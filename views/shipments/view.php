<?php $pageTitle = $shipment['title'] ?: 'Shipment #' . $shipmentId; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-ship me-2 text-primary"></i><?php echo htmlspecialchars($shipment['title'] ?: 'Shipment #' . $shipment['id']); ?></h4><small class="text-muted">Created <?php echo date('M d, Y H:i', strtotime($shipment['created_at'])); ?> | <?php echo count($containers); ?> container(s)</small></div>
    <div class="d-flex gap-2">
        <form method="POST" action="/track" class="d-inline" id="shipmentRefreshForm">
            <input type="hidden" name="shipment_id" value="<?php echo $shipment['id']; ?>">
            <?php foreach ($containers as $c): ?>
            <input type="hidden" name="container_ids[]" value="<?php echo $c['id']; ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-success" id="shipmentRefreshBtn">
                <i class="fas fa-sync-alt me-1"></i>Refresh Tracking
            </button>
        </form>
        <a href="/shipments/edit?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-primary"><i class="fas fa-edit me-1"></i>Edit</a>
        <a href="/shipments/add?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-success"><i class="fas fa-plus me-1"></i>Add Containers</a>
        <form method="POST" action="/shipments/delete" class="d-inline" id="deleteShipmentForm">
            <input type="hidden" name="id" value="<?php echo $shipment['id']; ?>">
            <button type="button" class="btn btn-outline-danger" onclick="confirmAction('Delete this shipment and unlink all containers? This cannot be undone.', function() { document.getElementById('deleteShipmentForm').submit(); })"><i class="fas fa-trash me-1"></i>Delete</button>
        </form>
        <a href="/shipments" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card-modern p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-4"><div class="detail-label">Bill of Lading</div><div class="detail-value font-mono"><?php echo htmlspecialchars($shipment['bill_of_lading'] ?: '-'); ?></div></div>
        <div class="col-md-4"><div class="detail-label">PO #</div><div class="detail-value font-mono"><?php echo htmlspecialchars($shipment['po_number'] ?: '-'); ?></div></div>
        <div class="col-md-4"><div class="detail-label">Customer</div><div class="detail-value"><?php echo htmlspecialchars($shipment['customer_name'] ?: '-'); ?></div></div>
        <div class="col-md-4"><div class="detail-label">Destination</div><div class="detail-value"><?php echo htmlspecialchars($shipment['destination'] ?: '-'); ?></div></div>
        <div class="col-md-4"><div class="detail-label">Commodity</div><div class="detail-value"><?php echo htmlspecialchars($shipment['commodity'] ?: '-'); ?></div></div>
        <div class="col-md-4"><div class="detail-label">Notes</div><div class="detail-value"><?php echo nl2br(htmlspecialchars($shipment['notes'] ?: '-')); ?></div></div>
        <?php if ($shipment['tags']): ?><div class="col-12"><div class="detail-label">Tags</div><?php foreach (parseTagsString($shipment['tags']) as $t): ?><span class="badge bg-light text-dark border tag-pill"><?php echo htmlspecialchars($t); ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
</div>

<div class="card-modern p-0">
    <div class="p-3 border-bottom"><h6 class="fw-bold mb-0"><i class="fas fa-box me-2 text-primary"></i>Containers</h6></div>
    <?php if (!empty($containers)): ?>
    <div class="table-container rounded-0 border-0">
        <table class="table mb-0 shipment-view-table">
            <thead class="table-light"><tr><th>Container</th><th>Status</th><th>State</th><th>Last Event</th><th>Location</th><th>Event Time</th><th>ETA Station</th><th>ETA</th><th>LFD</th><th>Customs</th><th style="width:100px;"></th></tr></thead>
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
                    $isOutGate = ($snap && (strpos(strtolower($snap['last_event'] ?? ''), 'out-gate') !== false || strpos(strtolower($snap['last_event'] ?? ''), 'out gate') !== false));
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td class="font-mono fw-bold"><a href="/container?id=<?php echo $c['id']; ?>"><?php echo containerFormatDisplay($c['container_number']); ?></a></td>
                    <td><span class="badge-soft <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($snap['waybill_status'] ?? 'N/A'); ?></span></td>
                    <td class="small"><?php echo formatLoadEmpty($snap['load_empty'] ?? ''); ?></td>
                    <td class="small text-truncate" style="max-width:140px;" title="<?php echo htmlspecialchars($snap['last_event'] ?? ''); ?>"><?php if ($isOutGate): ?><span class="text-muted fst-italic"><?php echo htmlspecialchars($snap['last_event'] ?? '-'); ?></span><?php else: ?><?php echo htmlspecialchars($snap['last_event'] ?? '-'); ?><?php endif; ?></td>
                    <td class="fw-bold small"><?php echo htmlspecialchars($snap['last_event_location'] ?? '-'); ?></td>
                    <td class="small"><?php if (!empty($snap['last_event_time_local'])): ?><?php echo date('M d H:i', strtotime($snap['last_event_time_local'])); ?><span class="tz-bubble"><?php echo htmlspecialchars($snap['last_event_timezone'] ?? ''); ?></span><?php else: ?>-<?php endif; ?></td>
                    <td class="small fw-bold"><?php echo htmlspecialchars($snap['eta_station'] ?? '-'); ?></td>
                    <td class="small fw-bold text-primary"><?php if (!empty($snap['eta_local'])): ?><?php echo date('M d H:i', strtotime($snap['eta_local'])); ?><span class="tz-bubble"><?php echo htmlspecialchars($snap['eta_timezone'] ?? ''); ?></span><?php if (isWeekend($snap['eta_local'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?><?php else: ?>-<?php endif; ?></td>
                    <td class="small"><?php if (!empty($snap['last_free_day'])): ?><?php echo date('M d', strtotime($snap['last_free_day'])); ?><?php if (isWeekend($snap['last_free_day'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?><?php else: ?>-<?php endif; ?></td>
                    <td class="small"><?php if ($cs === 'hold'): ?><span class="text-danger fw-bold"><i class="fas fa-lock me-1"></i>HOLD</span><?php elseif ($cs === 'cleared'): ?><span class="text-success"><i class="fas fa-check-circle me-1"></i>OK</span><?php else: ?><?php echo htmlspecialchars(mb_strimwidth($customsRaw, 0, 20, '...')); ?><?php endif; ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/container?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details"><i class="fas fa-eye"></i></a>
                            <form method="POST" action="/shipments/unlink" class="d-inline" id="unlinkForm-<?php echo $c['id']; ?>">
                                <input type="hidden" name="shipment_id" value="<?php echo $shipment['id']; ?>">
                                <input type="hidden" name="container_id" value="<?php echo $c['id']; ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Remove from shipment" onclick="confirmAction('Remove this container from the shipment?', function() { document.getElementById('unlinkForm-<?php echo $c['id']; ?>').submit(); })"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-4 text-center text-muted"><i class="fas fa-box-open fa-2x mb-2"></i><p class="mb-0">No containers in this shipment yet.</p><a href="/shipments/add?id=<?php echo $shipment['id']; ?>" class="btn btn-primary mt-2"><i class="fas fa-plus me-1"></i>Add Containers</a></div>
    <?php endif; ?>
</div>
