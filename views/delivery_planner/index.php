<?php $pageTitle = 'Delivery Planner'; ?>

<style>
.dp-table th { background: #f8f9fa; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; padding: 8px 10px; }
.dp-table td { padding: 6px 10px; vertical-align: middle; font-size: 0.82rem; }
.dp-table .group-header td { background: #f0f0f0; font-weight: 700; padding: 8px 10px; border-bottom: 2px solid #dee2e6; font-size: 0.85rem; }
.dp-input { width: 130px; padding: 4px 8px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.78rem; font-family: 'JetBrains Mono', monospace; }
.dp-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(218,41,28,0.1); }
.dp-input-sm { width: 60px; padding: 4px 6px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.78rem; font-family: 'JetBrains Mono', monospace; text-align: center; }
.dp-input-sm:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(218,41,28,0.1); }
.dp-toast { position: fixed; bottom: 80px; right: 20px; background: #198754; color: #fff; padding: 8px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 9999; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
.dp-toast.show { opacity: 1; }
.detention-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.detention-ok { background: #d1e7dd; color: #0f5132; }
.detention-warn { background: #fff3cd; color: #664d03; }
.detention-danger { background: #f8d7da; color: #842029; }
.dp-filter-active { background: var(--primary); color: #fff; border-color: var(--primary); }
tr.dp-hidden { display: none; }
</style>

<div id="dpToast" class="dp-toast"></div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Delivery Planner</h4>
        <small class="text-muted">Select containers, set delivery dates, manage detention. All changes save automatically.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select class="form-select form-select-sm" id="dpFilter" style="width:auto; font-size:0.8rem;">
            <option value="">All Containers (<?php echo count($allContainers); ?>)</option>
            <option value="has-plan" <?php echo $filter === 'has-plan' ? 'selected' : ''; ?>>Has Plan</option>
            <option value="no-plan" <?php echo $filter === 'no-plan' ? 'selected' : ''; ?>>Not Planned</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="dpToggleHidden" title="Hide unselected rows"><i class="fas fa-eye me-1"></i>Show Selected Only</button>
        <button class="btn btn-sm btn-outline-success" onclick="copySelectedHtml()"><i class="fas fa-code me-1"></i>Copy HTML</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="copySelectedText()"><i class="fas fa-file-alt me-1"></i>Copy Text</button>
    </div>
</div>

<div class="card-modern p-0">
    <div class="table-responsive">
        <table class="table dp-table mb-0" id="dpTable">
            <thead>
                <tr>
                    <th style="width:40px;" class="text-center"><input type="checkbox" class="form-check-input" id="dpSelectAll"></th>
                    <th>Container</th>
                    <th>ETA</th>
                    <th>LFD</th>
                    <th>Delivery Date</th>
                    <th>Delivery Time</th>
                    <th class="text-center">Free Days</th>
                    <th class="text-center">Working Days</th>
                    <th class="text-center">DOI</th>
                    <th class="text-center">Detention</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $groups = [];
                foreach ($filteredContainers as $c) {
                    $sid = $c['shipment_id'] ?? null;
                    $key = $sid ? ($shipmentTitles[$sid] ?? 'Shipment #' . $sid) : '(no shipment)';
                    $groups[$key][] = $c;
                }
                ksort($groups);
                foreach ($groups as $groupName => $groupContainers):
                ?>
                <tr class="group-header">
                    <td class="text-center"><input type="checkbox" class="form-check-input group-check" data-group="<?php echo htmlspecialchars($groupName); ?>"></td>
                    <td colspan="9"><i class="fas fa-ship me-2 text-primary"></i><?php echo htmlspecialchars($groupName); ?> <span class="badge bg-secondary ms-1"><?php echo count($groupContainers); ?></span></td>
                </tr>
                <?php foreach ($groupContainers as $c):
                    $snap = $containerTracking[$c['id']] ?? null;
                    $plan = $deliveryPlans[$c['id']] ?? null;
                    $freeDays = (int)($plan['free_days'] ?? 0);
                    $useWorking = !empty($plan['use_working_days']);
                    $doi = !empty($plan['day_of_interchange']);
                    $calcLfd = null;
                    if (!empty($snap['eta_local'])) {
                        $etaDt = new DateTime($snap['eta_local']);
                        $addDays = max(0, $freeDays - 1);
                        if ($doi) $etaDt->modify('+1 day');
                        if ($useWorking) {
                            $added = 0;
                            while ($added < $addDays) {
                                $etaDt->modify('+1 day');
                                if ((int)$etaDt->format('N') <= 5) $added++;
                            }
                        } else {
                            $etaDt->modify("+{$addDays} days");
                        }
                        $calcLfd = $etaDt;
                    }
                ?>
                <tr class="dp-row" data-cid="<?php echo $c['id']; ?>">
                    <td class="text-center"><input type="checkbox" class="form-check-input row-check dp-cb" value="<?php echo $c['id']; ?>"></td>
                    <td class="font-mono fw-bold"><a href="/container?id=<?php echo $c['id']; ?>" class="text-decoration-none"><?php echo containerFormatDisplay($c['container_number']); ?></a></td>
                    <td class="small fw-bold text-primary"><?php if (!empty($snap['eta_local'])): ?><?php echo date('M d H:i', strtotime($snap['eta_local'])); ?><span class="tz-bubble"><?php echo htmlspecialchars($snap['eta_timezone'] ?? ''); ?></span><?php else: ?>-<?php endif; ?></td>
                    <td class="small"><?php if (!empty($snap['last_free_day'])): ?><?php echo date('M d', strtotime($snap['last_free_day'])); ?><?php else: ?>-<?php endif; ?></td>
                    <td><input type="date" class="dp-input dp-date" data-cid="<?php echo $c['id']; ?>" value="<?php echo htmlspecialchars($plan['delivery_date'] ?? ''); ?>"></td>
                    <td><input type="time" class="dp-input dp-time" data-cid="<?php echo $c['id']; ?>" value="<?php echo htmlspecialchars($plan['delivery_time'] ?? ''); ?>"></td>
                    <td class="text-center"><input type="number" min="0" max="99" class="dp-input-sm dp-free-days" data-cid="<?php echo $c['id']; ?>" value="<?php echo $freeDays; ?>"></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input dp-working-days" data-cid="<?php echo $c['id']; ?>" <?php echo $useWorking ? 'checked' : ''; ?>></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input dp-doi" data-cid="<?php echo $c['id']; ?>" <?php echo $doi ? 'checked' : ''; ?>></td>
                    <td class="small fw-bold" data-cid="<?php echo $c['id']; ?>"><?php if ($calcLfd): ?><span class="text-primary"><?php echo $calcLfd->format('D, d/M/Y'); ?></span><?php else: ?>-<?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    var saveTimers = {};

    var selectAll = document.getElementById('dpSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.dp-cb').forEach(function(cb) {
                if (!cb.closest('tr').classList.contains('dp-hidden')) cb.checked = checked;
            });
            document.querySelectorAll('.group-check').forEach(function(gc) { gc.checked = checked; });
        });
    }

    document.querySelectorAll('.group-check').forEach(function(gc) {
        gc.addEventListener('change', function() {
            var group = this.getAttribute('data-group');
            var checked = this.checked;
            var rows = document.querySelectorAll('.dp-row');
            var inGroup = false;
            rows.forEach(function(row) {
                var hdr = row.previousElementSibling;
                if (hdr && hdr.classList.contains('group-header')) {
                    var gc2 = hdr.querySelector('.group-check');
                    inGroup = (gc2 === gc);
                }
                if (inGroup) { var cb = row.querySelector('.dp-cb'); if (cb) cb.checked = checked; }
            });
        });
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('dp-date') || e.target.classList.contains('dp-time') ||
            e.target.classList.contains('dp-free-days') || e.target.classList.contains('dp-working-days') ||
            e.target.classList.contains('dp-doi')) {
            var cid = e.target.getAttribute('data-cid');
            if (cid) { clearTimeout(saveTimers[cid]); saveTimers[cid] = setTimeout(function() { saveDelivery(cid); }, 400); }
        }
    });

    function saveDelivery(cid) {
        var dateInput = document.querySelector('.dp-date[data-cid="' + cid + '"]');
        var timeInput = document.querySelector('.dp-time[data-cid="' + cid + '"]');
        var freeInput = document.querySelector('.dp-free-days[data-cid="' + cid + '"]');
        var workingInput = document.querySelector('.dp-working-days[data-cid="' + cid + '"]');
        var doiInput = document.querySelector('.dp-doi[data-cid="' + cid + '"]');
        var fd = new FormData();
        fd.append('container_id', cid);
        fd.append('delivery_date', dateInput ? dateInput.value : '');
        fd.append('delivery_time', timeInput ? timeInput.value : '');
        fd.append('free_days', freeInput ? freeInput.value : '0');
        if (workingInput && workingInput.checked) fd.append('use_working_days', '1');
        if (doiInput && doiInput.checked) fd.append('day_of_interchange', '1');
        fetch('/delivery-planner/save', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    recalcLfd(cid);
                    showToast('Saved', 'success');
                }
            })
            .catch(function() {});
    }

    function recalcLfd(cid) {
        var row = document.querySelector('.dp-row[data-cid="' + cid + '"]');
        if (!row) return;
        var cells = row.querySelectorAll('td');
        var etaText = cells[2] ? cells[2].textContent.trim() : '';
        var freeInput = document.querySelector('.dp-free-days[data-cid="' + cid + '"]');
        var workingInput = document.querySelector('.dp-working-days[data-cid="' + cid + '"]');
        var doiInput = document.querySelector('.dp-doi[data-cid="' + cid + '"]');
        var freeDays = freeInput ? parseInt(freeInput.value) || 0 : 0;
        var useWorking = workingInput ? workingInput.checked : false;
        var doi = doiInput ? doiInput.checked : false;
        var lfdCell = cells[9];
        if (!lfdCell || !etaText || etaText === '-') { if (lfdCell) lfdCell.innerHTML = '-'; return; }
        var etaMatch = etaText.match(/([A-Z][a-z]{2})\s+(\d{1,2})\s+(\d{1,2}):(\d{2})/);
        if (!etaMatch) return;
        var months = {Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};
        var d = new Date(2026, months[etaMatch[1]], parseInt(etaMatch[2]));
        if (doi) d.setDate(d.getDate() + 1);
        var addDays = Math.max(0, freeDays - 1);
        if (useWorking) {
            var added = 0;
            while (added < addDays) {
                d.setDate(d.getDate() + 1);
                if (d.getDay() !== 0 && d.getDay() !== 6) added++;
            }
        } else {
            d.setDate(d.getDate() + addDays);
        }
        var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var monNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        lfdCell.innerHTML = '<span class="text-primary fw-bold">' + dayNames[d.getDay()] + ', ' + String(d.getDate()).padStart(2,'0') + '/' + monNames[d.getMonth()] + '/' + d.getFullYear() + '</span>';
    }

    function showToast(msg, type) {
        var t = document.getElementById('dpToast');
        if (!t) return;
        t.textContent = msg;
        t.style.background = type === 'success' ? '#198754' : '#dc3545';
        t.classList.add('show');
        clearTimeout(t._timer);
        t._timer = setTimeout(function() { t.classList.remove('show'); }, 2000);
    }

    document.getElementById('dpFilter').addEventListener('change', function() {
        var val = this.value;
        var params = new URLSearchParams(window.location.search);
        if (val) { params.set('filter', val); } else { params.delete('filter'); }
        window.location.search = params.toString();
    });

    var hideMode = false;
    document.getElementById('dpToggleHidden').addEventListener('click', function() {
        hideMode = !hideMode;
        this.innerHTML = hideMode ? '<i class="fas fa-eye-slash me-1"></i>Show All' : '<i class="fas fa-eye me-1"></i>Show Selected Only';
        this.classList.toggle('dp-filter-active', hideMode);
        var rows = document.querySelectorAll('.dp-row');
        rows.forEach(function(row) {
            var cb = row.querySelector('.dp-cb');
            if (hideMode && (!cb || !cb.checked)) {
                row.classList.add('dp-hidden');
            } else {
                row.classList.remove('dp-hidden');
            }
        });
        document.querySelectorAll('.group-header').forEach(function(hdr) {
            var nextRow = hdr.nextElementSibling;
            var hasVisible = false;
            while (nextRow && !nextRow.classList.contains('group-header')) {
                if (!nextRow.classList.contains('dp-hidden')) { hasVisible = true; break; }
                nextRow = nextRow.nextElementSibling;
            }
            hdr.style.display = hasVisible ? '' : 'none';
        });
    });

    function getSelectedRows() {
        var rows = [];
        document.querySelectorAll('.dp-cb:checked').forEach(function(cb) { var tr = cb.closest('tr'); if (tr) rows.push(tr); });
        return rows;
    }
    function stripHtml(s) { var d = document.createElement('div'); d.innerHTML = s; return d.textContent; }

    window.copySelectedHtml = function() {
        var rows = getSelectedRows();
        if (rows.length === 0) { alert('Select containers first.'); return; }
        var html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;width:100%;">';
        html += '<thead><tr style="background:#f2f2f2;"><th>Container</th><th>ETA</th><th>LFD</th><th>Delivery Date</th><th>Delivery Time</th><th>Free Days</th><th>Last Free Day</th></tr></thead><tbody>';
        rows.forEach(function(tr) {
            var cells = tr.querySelectorAll('td');
            html += '<tr>';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + stripHtml(cells[1].innerHTML) + '</td>';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + stripHtml(cells[2].innerHTML) + '</td>';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + stripHtml(cells[3].innerHTML) + '</td>';
            var dateVal = cells[4].querySelector('input') ? cells[4].querySelector('input').value : stripHtml(cells[4].innerHTML);
            var timeVal = cells[5].querySelector('input') ? cells[5].querySelector('input').value : stripHtml(cells[5].innerHTML);
            var freeVal = cells[6].querySelector('input') ? cells[6].querySelector('input').value : '0';
            var lfdVal = cells[9] ? stripHtml(cells[9].innerHTML) : '-';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + (dateVal || '-') + '</td>';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + (timeVal || '-') + '</td>';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + (freeVal || '0') + '</td>';
            html += '<td style="border:1px solid #ddd;padding:6px;">' + lfdVal + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        if (navigator.clipboard && navigator.clipboard.write) {
            var blob = new Blob([html], { type: 'text/html' });
            navigator.clipboard.write([new ClipboardItem({ 'text/html': blob, 'text/plain': new Blob([stripHtml(html)], { type: 'text/plain' }) })]).then(function() { showToast('HTML copied!', 'success'); }).catch(function() { fallbackCopy(html); });
        } else { fallbackCopy(html); }
    };

    window.copySelectedText = function() {
        var rows = getSelectedRows();
        if (rows.length === 0) { alert('Select containers first.'); return; }
        var lines = ['Container\tETA\tLFD\tDelivery Date\tDelivery Time\tFree Days\tLast Free Day'];
        rows.forEach(function(tr) {
            var cells = tr.querySelectorAll('td');
            var dateVal = cells[4].querySelector('input') ? cells[4].querySelector('input').value : stripHtml(cells[4].innerHTML);
            var timeVal = cells[5].querySelector('input') ? cells[5].querySelector('input').value : stripHtml(cells[5].innerHTML);
            var freeVal = cells[6].querySelector('input') ? cells[6].querySelector('input').value : '0';
            var lfdVal = cells[9] ? stripHtml(cells[9].innerHTML) : '-';
            lines.push(stripHtml(cells[1].innerHTML) + '\t' + stripHtml(cells[2].innerHTML) + '\t' + stripHtml(cells[3].innerHTML) + '\t' + (dateVal || '-') + '\t' + (timeVal || '-') + '\t' + (freeVal || '0') + '\t' + lfdVal);
        });
        navigator.clipboard.writeText(lines.join('\n')).then(function() { showToast('Text copied!', 'success'); }).catch(function() { fallbackCopy(lines.join('\n')); });
    };

    function fallbackCopy(text) {
        var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.left = '-9999px';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); showToast('Copied!', 'success'); } catch(e) { showToast('Failed to copy.', 'error'); }
        document.body.removeChild(ta);
    }
})();
</script>
