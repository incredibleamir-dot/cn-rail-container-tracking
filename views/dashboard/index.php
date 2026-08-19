<?php
$pageTitle = 'Dashboard';
?>

<div class="mb-4 d-flex gap-2 flex-wrap align-items-center">
    <a href="/shipments/create" class="btn btn-primary px-4 py-2" style="border-radius:10px;">
        <i class="fas fa-ship me-1"></i>New Shipment
    </a>
    <?php $trackParams = http_build_query(array_filter(['group' => $groupField ?? null, 'tag' => $filterTag ?? null, 'archived' => ($showArchived ?? false) ? '1' : null])); ?>
    <form method="POST" action="/track<?php echo $trackParams ? '?' . $trackParams : ''; ?>" class="d-inline">
        <input type="hidden" name="track_all" value="1">
        <button type="submit" class="btn btn-success px-4 py-2" style="border-radius:10px;" onclick="startLoadingAnimation()">
            <i class="fas fa-sync-alt me-1"></i>Track All
        </button>
    </form>
    <button type="button" class="btn btn-outline-primary px-4 py-2" style="border-radius:10px;" onclick="openShareModal()">
        <i class="fas fa-share-alt me-1"></i>Share Selected
    </button>
    <button type="button" class="btn btn-warning px-4 py-2" style="border-radius:10px;" id="planDeliveryBtn" onclick="goToDeliveryPlanner()">
        <i class="fas fa-calendar-alt me-1"></i>Plan Delivery
    </button>
    <span class="badge bg-light text-dark border ms-auto py-2 px-3 small auto-refresh-timer">
        <i class="fas fa-clock me-1"></i><span id="refreshCountdown">10:00</span>
    </span>
</div>

<?php if (!empty($allTags)): ?>
<div class="mb-3">
    <span class="text-muted small me-2 fw-bold"><i class="fas fa-tag me-1"></i>Tags:</span>
    <a href="/" class="badge <?php echo empty($filterTag) ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">All</a>
    <?php foreach ($allTags as $tag => $count): ?>
        <a href="/?tag=<?php echo urlencode($tag); ?>"
           class="badge <?php echo $filterTag === $tag ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1 mb-1"
           style="cursor:pointer;"><?php echo htmlspecialchars($tag); ?> <span class="ms-1"><?php echo $count; ?></span></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mb-3">
    <span class="text-muted small me-2 fw-bold"><i class="fas fa-layer-group me-1"></i>Group by:</span>
    <a href="/<?php echo !empty($filterTag) ? '?tag=' . urlencode($filterTag) : ''; ?>" class="badge <?php echo empty($groupField) ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;">None</a>
    <a href="/?group=shipment<?php echo !empty($filterTag) ? '&tag=' . urlencode($filterTag) : ''; ?>" class="badge <?php echo $groupField === 'shipment' ? 'bg-danger' : 'bg-light text-dark border'; ?> text-decoration-none me-1" style="cursor:pointer;"><i class="fas fa-ship me-1"></i>Shipment</a>
</div>

<?php if (!empty($containers)): ?>
    <?php
    $filteredContainers = $containers;
    if (!empty($filterTag)) {
        $filteredContainers = array_filter($containers, function($c) use ($filterTag) {
            return in_array($filterTag, parseTagsString($c['tags']));
        });
    }
    if (!empty($filteredContainers)):
    ?>
    <div class="card-modern p-0 animate__animated animate__fadeInUp">
        <div class="table-container d-none d-lg-block">
            <table class="table w-100 mb-0" id="watchlistTable">
                <thead>
                    <tr>
                        <th style="width:40px;" class="text-center"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>Container</th>
                        <th>Shipment</th>
                        <th>Status</th>
                        <th>State</th>
                        <th>Last Event</th>
                        <th>Location</th>
                        <th>Event Time</th>
                        <th>ETA Station</th>
                        <th>ETA</th>
                        <th>LFD</th>
                        <th>Customs</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $displayGroups = [];
                    if ($groupField === 'shipment' && !empty($groupedContainers)) {
                        foreach ($groupedContainers as $groupName => $group) {
                            $displayGroups[$groupName] = $group;
                        }
                    } else {
                        $displayGroups[''] = $filteredContainers;
                    }

                    foreach ($displayGroups as $groupName => $groupContainers):
                        if ($groupName !== ''):
                    ?>
                        <tr class="group-header"><td colspan="13" style="background:#f0f0f0;font-weight:700;padding:8px 12px;border-bottom:2px solid #dee2e6;"><i class="fas fa-ship me-2 text-primary"></i><?php echo htmlspecialchars($groupName); ?> <span class="badge bg-secondary ms-2"><?php echo count($groupContainers); ?></span></td></tr>
                    <?php endif; ?>
                    <?php foreach ($groupContainers as $c):
                        $latest = $snapshots[$c['id']] ?? null;
                        $st = strtolower($latest['waybill_status'] ?? '');
                        $badgeClass = 'bg-soft-gray';
                        if ($st === 'executing') $badgeClass = 'bg-soft-green';
                        elseif ($st === 'planned') $badgeClass = 'bg-soft-blue';
                        elseif ($st === 'pending') $badgeClass = 'bg-soft-yellow';

                        $customsRaw = $latest['customs_status'] ?? '';
                        $cs = customsStatus($customsRaw);
                        $rowClass = $cs === 'hold' ? 'row-inbond' : ($cs === 'cleared' ? 'row-released' : '');
                        $customsIcon = '';
                        if ($cs === 'cleared') $customsIcon = '<i class="fas fa-check-circle text-success ms-1" title="Cleared"></i>';
                        elseif ($cs === 'hold') $customsIcon = '<i class="fas fa-lock text-danger ms-1" title="Customs Hold"></i>';
                    ?>
                    <tr class="<?php echo $rowClass; ?>"
                        data-container="<?php echo htmlspecialchars(containerFormatDisplay($c['container_number'])); ?>"
                        data-shipment="<?php echo htmlspecialchars($shipmentTitles[$c['shipment_id']] ?? ''); ?>"
                        data-status="<?php echo htmlspecialchars($latest['waybill_status'] ?? 'N/A'); ?>"
                        data-state="<?php echo formatLoadEmpty($latest['load_empty'] ?? ''); ?>"
                        data-event="<?php echo htmlspecialchars($latest['last_event'] ?? '-'); ?>"
                        data-location="<?php echo htmlspecialchars($latest['last_event_location'] ?? '-'); ?>"
                        data-eventtime="<?php echo !empty($latest['last_event_time_local']) ? date('M d H:i', strtotime($latest['last_event_time_local'])) . ' ' . htmlspecialchars($latest['last_event_timezone'] ?? '') : '-'; ?>"
                        data-eta-station="<?php echo htmlspecialchars($latest['eta_station'] ?? '-'); ?>"
                        data-eta="<?php echo !empty($latest['eta_local']) ? date('M d H:i', strtotime($latest['eta_local'])) . ' ' . htmlspecialchars($latest['eta_timezone'] ?? '') : '-'; ?>"
                        data-lfd="<?php echo !empty($latest['last_free_day']) ? date('M d', strtotime($latest['last_free_day'])) : '-'; ?>"
                        data-customs="<?php echo $cs === 'hold' ? 'HOLD' : ($cs === 'cleared' ? 'OK' : htmlspecialchars($customsRaw ?: '-')); ?>">
                        <td class="text-center"><input type="checkbox" class="form-check-input row-check" value="<?php echo $c['id']; ?>"></td>
                        <td class="font-mono fw-bold">
                            <a href="/container?id=<?php echo $c['id']; ?>" class="container-link text-decoration-none">
                                <?php echo containerFormatDisplay($c['container_number']); ?>
                            </a>
                        </td>
                        <td class="small">
                            <?php if (!empty($c['shipment_id']) && !empty($shipmentTitles[$c['shipment_id']])): ?>
                                <a href="/shipments/view?id=<?php echo $c['shipment_id']; ?>" class="text-decoration-none small">
                                    <i class="fas fa-ship me-1 text-muted"></i><?php echo htmlspecialchars($shipmentTitles[$c['shipment_id']]); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-soft <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($latest['waybill_status'] ?? 'N/A'); ?></span></td>
                        <td class="small"><?php echo formatLoadEmpty($latest['load_empty'] ?? ''); ?></td>
                        <td class="small text-truncate" style="max-width:150px;" title="<?php echo htmlspecialchars($latest['last_event'] ?? ''); ?>">
                            <?php echo htmlspecialchars($latest['last_event'] ?? '-'); ?>
                        </td>
                        <td class="fw-bold small"><?php echo htmlspecialchars($latest['last_event_location'] ?? '-'); ?></td>
                        <td class="small">
                            <?php if (!empty($latest['last_event_time_local'])): ?>
                                <?php echo date('M d H:i', strtotime($latest['last_event_time_local'])); ?>
                                <span class="tz-bubble"><?php echo htmlspecialchars($latest['last_event_timezone'] ?? ''); ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="small fw-bold"><?php echo htmlspecialchars($latest['eta_station'] ?? '-'); ?></td>
                        <td class="small fw-bold text-primary">
                            <?php if (!empty($latest['eta_local'])): ?>
                                <?php echo date('M d H:i', strtotime($latest['eta_local'])); ?>
                                <span class="tz-bubble"><?php echo htmlspecialchars($latest['eta_timezone'] ?? ''); ?></span>
                                <?php if (isWeekend($latest['eta_local'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if (!empty($latest['last_free_day'])): ?>
                                <?php echo date('M d', strtotime($latest['last_free_day'])); ?>
                                <?php if (isWeekend($latest['last_free_day'])): ?><span class="weekend-bubble">Sat/Sun</span><?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($cs === 'hold'): ?>
                                <span class="text-danger fw-bold" title="<?php echo htmlspecialchars($customsRaw); ?>"><i class="fas fa-lock me-1"></i>HOLD</span>
                            <?php elseif ($cs === 'cleared'): ?>
                                <span class="text-success" title="<?php echo htmlspecialchars($customsRaw); ?>"><i class="fas fa-check-circle me-1"></i>OK</span>
                            <?php elseif (!empty($customsRaw)): ?>
                                <span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($customsRaw, 0, 25, '...')); ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <form method="POST" action="/track-single" class="d-inline track-single-form" data-id="<?php echo $c['id']; ?>">
                                    <input type="hidden" name="container_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Refresh"><i class="fas fa-sync-alt"></i></button>
                                </form>
                                <a href="/container?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Details"><i class="fas fa-eye"></i></a>
                                <button class="btn btn-sm btn-outline-secondary" title="Edit" onclick="openEditModal(<?php echo $c['id']; ?>)"><i class="fas fa-edit"></i></button>
                                <?php if ($showArchived): ?>
                                <form method="POST" action="/unarchive" class="d-inline">
                                    <input type="hidden" name="container_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Restore"><i class="fas fa-undo"></i></button>
                                </form>
                                <?php else: ?>
                                <form method="POST" action="/archive" class="d-inline">
                                    <input type="hidden" name="container_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Archive"><i class="fas fa-archive"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-view-container d-lg-none p-3">
            <?php foreach ($displayGroups as $groupName => $groupContainers):
                if ($groupName !== ''): ?>
                <div class="fw-bold mb-2 mt-3 text-primary"><i class="fas fa-ship me-1"></i><?php echo htmlspecialchars($groupName); ?> <span class="badge bg-secondary"><?php echo count($groupContainers); ?></span></div>
                <?php endif; ?>
            <?php foreach ($groupContainers as $c):
                $latest = $snapshots[$c['id']] ?? null;
                $customsRaw = $latest['customs_status'] ?? '';
                $cs = customsStatus($customsRaw);
                $cardClass = $cs === 'hold' ? 'card-inbond' : ($cs === 'cleared' ? 'card-released' : '');
            ?>
            <div class="tracking-card <?php echo $cardClass; ?>">
                <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                    <a href="/container?id=<?php echo $c['id']; ?>" class="font-mono fw-bold text-primary text-decoration-none">
                        <?php echo containerFormatDisplay($c['container_number']); ?>
                    </a>
                    <?php if ($groupName === '' && !empty($c['shipment_id']) && !empty($shipmentTitles[$c['shipment_id']])): ?>
                        <div class="small text-muted"><i class="fas fa-ship me-1"></i><?php echo htmlspecialchars($shipmentTitles[$c['shipment_id']]); ?></div>
                    <?php endif; ?>
                    <div class="d-flex gap-1">
                        <form method="POST" action="/track-single" class="d-inline track-single-form"><input type="hidden" name="container_id" value="<?php echo $c['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-sync-alt"></i></button></form>
                        <a href="/container?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?php echo $c['id']; ?>)"><i class="fas fa-edit"></i></button>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6"><div class="t-label">Status</div><div class="t-val"><?php echo htmlspecialchars($latest['waybill_status'] ?? 'N/A'); ?></div></div>
                    <div class="col-6"><div class="t-label">State</div><div class="t-val"><?php echo formatLoadEmpty($latest['load_empty'] ?? ''); ?></div></div>
                    <div class="col-6"><div class="t-label">Location</div><div class="t-val"><?php echo htmlspecialchars($latest['last_event_location'] ?? '-'); ?></div></div>
                    <div class="col-6"><div class="t-label">Event</div><div class="t-val"><?php echo htmlspecialchars($latest['last_event'] ?? '-'); ?></div></div>
                    <div class="col-6"><div class="t-label">ETA</div><div class="t-val text-primary"><?php echo !empty($latest['eta_local']) ? date('M d H:i', strtotime($latest['eta_local'])) : '-'; ?></div></div>
                    <div class="col-6"><div class="t-label">LFD</div><div class="t-val"><?php echo !empty($latest['last_free_day']) ? date('M d', strtotime($latest['last_free_day'])) : '-'; ?></div></div>
                    <?php if ($cs === 'hold'): ?>
                    <div class="col-12"><span class="text-danger fw-bold small"><i class="fas fa-lock me-1"></i>Customs Hold: <?php echo htmlspecialchars($customsRaw); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-filter fa-3x text-muted opacity-25 mb-3"></i>
            <p class="text-muted">No containers match tag "<?php echo htmlspecialchars($filterTag); ?>"</p>
            <a href="/" class="btn btn-outline-primary">Show All</a>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="text-center py-5 animate__animated animate__fadeIn">
        <i class="fas fa-train fa-3x text-muted opacity-25 mb-3"></i>
        <h5 class="text-muted">No containers in your watchlist</h5>
        <p class="text-muted">Add container numbers above to start tracking.</p>
    </div>
<?php endif; ?>

<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-centered" style="max-width:95vw;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title"><i class="fas fa-share-alt me-2"></i>Container Tracking Summary</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom bg-light text-muted small" id="shareTimestamp"></div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="shareTable">
                        <thead class="table-light">
                            <tr><th>Container</th><th>Shipment</th><th>Status</th><th>State</th><th>Last Event</th><th>Location</th><th>Event Time</th><th>ETA Station</th><th>ETA</th><th>LFD</th><th>Customs</th></tr>
                        </thead>
                        <tbody id="shareTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <small class="text-muted me-auto">CN Track &mdash; Avancer International Freight System Inc.</small>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
