<?php $pageTitle = 'Container ' . $container['container_number']; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0 font-mono">
            <i class="fas fa-box me-2 text-primary"></i><?php echo containerFormatDisplay($container['container_number']); ?>
        </h4>
        <small class="text-muted">Added <?php echo date('M d, Y H:i', strtotime($container['added_at'])); ?> | <?php echo $snapshotCount; ?> tracking snapshot(s)</small>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="/track-single" class="d-inline track-single-form" data-id="<?php echo $container['id']; ?>">
            <input type="hidden" name="container_id" value="<?php echo $container['id']; ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
        </form>
        <a href="/" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-modern p-4">
            <h6 class="fw-bold mb-3"><i class="fas fa-satellite-dish me-2 text-primary"></i>Current Status</h6>
            <?php if ($latest): ?>
            <?php
                $st = strtolower($latest['waybill_status'] ?? '');
                $badgeClass = 'bg-soft-gray';
                if ($st === 'executing') $badgeClass = 'bg-soft-green';
                elseif ($st === 'planned') $badgeClass = 'bg-soft-blue';
                elseif ($st === 'pending') $badgeClass = 'bg-soft-yellow';
                $customsRaw = $latest['customs_status'] ?? '';
                $cs = customsStatus($customsRaw);
            ?>
            <div class="row g-3">
                <div class="col-md-4"><div class="detail-label">Status</div><div><span class="badge-soft <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($latest['waybill_status'] ?? 'N/A'); ?></span></div></div>
                <div class="col-md-4"><div class="detail-label">Load State</div><div class="detail-value"><?php echo formatLoadEmpty($latest['load_empty'] ?? ''); ?></div></div>
                <div class="col-md-4"><div class="detail-label">Last Event</div><div class="detail-value"><?php echo htmlspecialchars($latest['last_event'] ?? '-'); ?></div></div>
                <div class="col-md-4"><div class="detail-label">Event Location</div><div class="detail-value fw-bold"><?php echo htmlspecialchars($latest['last_event_location'] ?? '-'); ?></div></div>
                <div class="col-md-4"><div class="detail-label">Event Time</div><div class="detail-value"><?php if (!empty($latest['last_event_time_local'])): ?><?php echo date('M d, Y H:i', strtotime($latest['last_event_time_local'])); ?><span class="tz-bubble"><?php echo htmlspecialchars($latest['last_event_timezone'] ?? ''); ?></span><?php else: ?>-<?php endif; ?></div></div>
                <div class="col-md-4"><div class="detail-label">ETA Station</div><div class="detail-value fw-bold"><?php echo htmlspecialchars($latest['eta_station'] ?? '-'); ?></div></div>
                <div class="col-md-4"><div class="detail-label">ETA</div><div class="detail-value text-primary fw-bold"><?php if (!empty($latest['eta_local'])): ?><?php echo date('M d, Y H:i', strtotime($latest['eta_local'])); ?><span class="tz-bubble"><?php echo htmlspecialchars($latest['eta_timezone'] ?? ''); ?></span><?php if (isWeekend($latest['eta_local'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?><?php else: ?>-<?php endif; ?></div></div>
                <div class="col-md-4"><div class="detail-label">Last Free Day</div><div class="detail-value"><?php if (!empty($latest['last_free_day'])): ?><?php echo date('M d, Y', strtotime($latest['last_free_day'])); ?><?php if (isWeekend($latest['last_free_day'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?><?php else: ?>-<?php endif; ?></div></div>
                <div class="col-md-4"><div class="detail-label">Last Checked</div><div class="detail-value small"><?php echo $latest['checked_at']; ?></div></div>
            </div>

            <hr>
            <h6 class="fw-bold mb-3"><i class="fas fa-passport me-2 text-primary"></i>Customs</h6>
            <?php
                $rawApi = \App\Models\TrackingHistory::getLatestRawApi($container['id']);
                $allCustoms = [];
                if ($rawApi) {
                    $equip = $rawApi['ThirdPartyIntermodalShipment']['Equipment'][0] ?? [];
                    $allCustoms = $equip['CustomsHolds'] ?? [];
                    if (empty($allCustoms) && !empty($equip['CustomsHold'])) $allCustoms = [$equip['CustomsHold']];
                }
            ?>
            <?php if (!empty($allCustoms)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>Status</th><th>Description</th><th>Timestamp</th><th>Direction</th></tr></thead>
                        <tbody>
                        <?php foreach ($allCustoms as $ch): $desc = $ch['Description'] ?? ''; $chStatus = customsStatus($desc); ?>
                            <tr>
                                <td><?php if ($chStatus === 'hold'): ?><span class="badge bg-danger">HOLD</span><?php elseif ($chStatus === 'cleared'): ?><span class="badge bg-success">CLEARED</span><?php else: ?><span class="badge bg-secondary">-</span><?php endif; ?></td>
                                <td class="small"><?php echo htmlspecialchars($desc); ?></td>
                                <td class="small"><?php echo !empty($ch['Timestamp']) ? htmlspecialchars($ch['Timestamp']) : '-'; ?></td>
                                <td class="small"><?php echo htmlspecialchars($ch['Direction'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($cs === 'hold'): ?>
                    <div class="alert alert-danger py-2 mb-0 mt-2"><i class="fas fa-lock me-2"></i><strong>Under Customs Hold</strong><div class="small mt-1"><?php echo htmlspecialchars($customsRaw); ?><?php echo !empty($latest['customs_timestamp']) ? ' — ' . htmlspecialchars($latest['customs_timestamp']) : ''; ?></div></div>
                <?php elseif ($cs === 'cleared'): ?>
                    <div class="alert alert-success py-2 mb-0 mt-2"><i class="fas fa-check-circle me-2"></i><strong>Customs Cleared</strong><div class="small mt-1"><?php echo htmlspecialchars($customsRaw); ?><?php echo !empty($latest['customs_timestamp']) ? ' — ' . htmlspecialchars($latest['customs_timestamp']) : ''; ?></div></div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted small mb-0">No customs information available.</p>
            <?php endif; ?>
            <?php else: ?>
                <p class="text-muted">No tracking data yet. Click Refresh to fetch from CN API.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-modern p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-tag me-2 text-primary"></i>Details</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?php echo $container['id']; ?>)"><i class="fas fa-edit"></i></button>
            </div>
            <div class="mb-2"><div class="detail-label">Bill of Lading</div><div class="detail-value font-mono"><?php echo htmlspecialchars($container['bill_of_lading'] ?: '-'); ?></div></div>
            <div class="mb-2"><div class="detail-label">PO #</div><div class="detail-value font-mono"><?php echo htmlspecialchars($container['po_number'] ?: '-'); ?></div></div>
            <div class="mb-2"><div class="detail-label">Customer</div><div class="detail-value"><?php echo htmlspecialchars($container['customer_name'] ?: '-'); ?></div></div>
            <div class="mb-2"><div class="detail-label">Destination</div><div class="detail-value"><?php echo htmlspecialchars($container['destination'] ?: '-'); ?></div></div>
            <div class="mb-2"><div class="detail-label">Commodity</div><div class="detail-value"><?php echo htmlspecialchars($container['commodity'] ?: '-'); ?></div></div>
            <div class="mb-2"><div class="detail-label">Notes</div><div class="detail-value"><?php echo nl2br(htmlspecialchars($container['notes'] ?: '-')); ?></div></div>
            <?php if ($container['tags']): ?>
            <div class="mb-2"><div class="detail-label">Tags</div><div><?php foreach (parseTagsString($container['tags']) as $t): ?><span class="badge bg-light text-dark border tag-pill"><?php echo htmlspecialchars($t); ?></span><?php endforeach; ?></div></div>
            <?php endif; ?>
        </div>
        <?php if ($latest): ?>
        <div class="card-modern p-4">
            <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>History</h6>
            <div class="mb-2"><span class="text-muted small">Snapshots:</span> <strong><?php echo $snapshotCount; ?></strong></div>
            <?php if ($snapshotCount > 0): ?>
            <a href="#history-section" class="btn btn-sm btn-outline-primary w-100 mt-2"><i class="fas fa-history me-1"></i>View Full History</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($snapshotCount > 0): ?>
<div class="card-modern p-0 mt-4" id="history-section">
    <div class="p-3 border-bottom">
        <h6 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Tracking History (<?php echo $snapshotCount; ?> snapshots)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:0.8rem;">
            <thead class="table-light"><tr><th>Date Checked</th><th>Status</th><th>State</th><th>Event</th><th>Location</th><th>Event Time</th><th>ETA</th><th>LFD</th><th>Customs</th></tr></thead>
            <tbody>
                <?php foreach ($history as $snap):
                    $sc = customsStatus($snap['customs_status'] ?? '');
                    $snapRowClass = $sc === 'hold' ? 'row-inbond' : ($sc === 'cleared' ? 'row-released' : '');
                ?>
                <tr class="<?php echo $snapRowClass; ?>">
                    <td><?php echo $snap['checked_at']; ?></td>
                    <td><?php echo htmlspecialchars($snap['waybill_status'] ?? '-'); ?></td>
                    <td><?php echo formatLoadEmpty($snap['load_empty'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($snap['last_event'] ?? '-'); ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($snap['last_event_location'] ?? '-'); ?></td>
                    <td><?php echo !empty($snap['last_event_time_local']) ? date('M d H:i', strtotime($snap['last_event_time_local'])) : '-'; ?></td>
                    <td><?php if (!empty($snap['eta_local'])): ?><?php echo date('M d H:i', strtotime($snap['eta_local'])); ?><span class="tz-bubble"><?php echo htmlspecialchars($snap['eta_timezone'] ?? ''); ?></span><?php else: ?>-<?php endif; ?></td>
                    <td><?php echo !empty($snap['last_free_day']) ? date('M d', strtotime($snap['last_free_day'])) : '-'; ?></td>
                    <td><?php if ($sc === 'hold'): ?><span class="text-danger fw-bold"><i class="fas fa-lock"></i></span><?php elseif ($sc === 'cleared'): ?><span class="text-success"><i class="fas fa-check-circle"></i></span><?php else: ?><?php echo htmlspecialchars(mb_strimwidth($snap['customs_status'] ?? '', 0, 20, '...')); ?><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Container Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="container_id" id="edit_container_id" value="<?php echo $container['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-bold small">Bill of Lading #</label><input type="text" name="bill_of_lading" id="edit_bol" class="form-control form-control-sm" value="<?php echo htmlspecialchars($container['bill_of_lading']); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-bold small">PO #</label><input type="text" name="po_number" id="edit_po" class="form-control form-control-sm" value="<?php echo htmlspecialchars($container['po_number']); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-bold small">Customer / Shipper</label><input type="text" name="customer_name" id="edit_customer" class="form-control form-control-sm" value="<?php echo htmlspecialchars($container['customer_name']); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-bold small">Destination</label><input type="text" name="destination" id="edit_destination" class="form-control form-control-sm" value="<?php echo htmlspecialchars($container['destination']); ?>"></div>
                        <div class="col-12"><label class="form-label fw-bold small">Commodity</label><input type="text" name="commodity" id="edit_commodity" class="form-control form-control-sm" value="<?php echo htmlspecialchars($container['commodity']); ?>"></div>
                        <div class="col-12"><label class="form-label fw-bold small">Notes</label><textarea name="notes" id="edit_notes" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($container['notes']); ?></textarea></div>
                        <div class="col-12"><label class="form-label fw-bold small">Tags</label><input type="text" name="tags" id="edit_tags" class="form-control form-control-sm" value="<?php echo htmlspecialchars($container['tags']); ?>" placeholder="Comma separated"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
