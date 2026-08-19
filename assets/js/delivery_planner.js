(function() {
    var debounceTimer, saveTimers = {};

    var searchEl = document.getElementById('containerSearch');
    var filterEl = document.getElementById('filterShipment');
    if (!searchEl || !filterEl) return;

    // ─── Search Filter ───
    searchEl.addEventListener('input', function() {
        applyFilters();
    });

    // ─── Shipment Filter ───
    filterEl.addEventListener('change', function() {
        applyFilters();
    });

    function applyFilters() {
        var q = document.getElementById('containerSearch').value.toLowerCase().trim();
        var shipId = document.getElementById('filterShipment').value;
        document.querySelectorAll('.container-list-scroll .form-check').forEach(function(el) {
            var show = true;
            var txt = el.getAttribute('data-search') || '';
            if (q !== '' && txt.indexOf(q) === -1) show = false;
            if (shipId !== '' && el.getAttribute('data-shipment') !== shipId) show = false;
            el.style.display = show ? '' : 'none';
        });
        updateSelectedCount();
    }

    // ─── Select All / None ───
    window.selectAllContainers = function(select) {
        document.querySelectorAll('.planner-container-cb').forEach(function(cb) {
            var parent = cb.closest('.form-check');
            if (parent && parent.style.display !== 'none') {
                cb.checked = select;
            }
        });
        updateSelectedCount();
        refreshPreview();
    };

    // ─── Update Selected Count ───
    function updateSelectedCount() {
        var count = document.querySelectorAll('.planner-container-cb:checked').length;
        document.getElementById('selectedCount').innerText = count + ' selected';
    }

    document.querySelectorAll('.planner-container-cb').forEach(function(cb) {
        cb.addEventListener('change', function() {
            updateSelectedCount();
            refreshPreview();
        });
    });
    applyFilters();

    // ─── Delivery date/time change → save via event delegation ───
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('dp-date') || e.target.classList.contains('dp-time')) {
            var containerId = e.target.getAttribute('data-cid');
            if (containerId) {
                clearTimeout(saveTimers[containerId]);
                saveTimers[containerId] = setTimeout(function() {
                    saveSingleDelivery(containerId);
                }, 400);
            }
        }
    });

    // ─── Refresh Preview ───
    window.refreshPreview = function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doRefresh, 300);
    };

    function doRefresh() {
        var containerIds = [];
        document.querySelectorAll('.planner-container-cb:checked').forEach(function(cb) {
            containerIds.push(cb.value);
        });
        if (containerIds.length === 0) {
            document.getElementById('previewContainer').innerHTML = '<p class="text-muted text-center p-4 mb-0">Select containers above.</p>';
            window._lastPreviewData = null;
            return;
        }

        var includeDel = document.getElementById('includeDelivery').checked;

        var fd = new FormData();
        fd.append('container_ids', JSON.stringify(containerIds));

        fetch('?page=delivery-planner/data', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    renderPreview(data, includeDel);
                    window._lastPreviewData = data;
                }
            })
            .catch(function() {});
    }

    function renderPreview(data, includeDel) {
        var container = document.getElementById('previewContainer');
        if (!data.rows || data.rows.length === 0) {
            container.innerHTML = '<p class="text-muted text-center p-4 mb-0">No data.</p>';
            return;
        }

        var cols = ['container_number', 'eta_local', 'last_free_day'];
        if (includeDel) {
            cols.push('delivery_date');
            cols.push('delivery_time');
        }

        var html = '<table class="preview-table"><thead><tr>';
        var headerNames = data.headers;
        var shownHeaders = includeDel ? headerNames : headerNames.slice(0, 3);
        shownHeaders.forEach(function(h) {
            html += '<th>' + h + '</th>';
        });
        html += '</tr></thead><tbody>';

        data.rows.forEach(function(r) {
            html += buildRow(r, cols, includeDel);
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function buildRow(r, cols, includeDel) {
        var cid = r._container_id || '';
        var html = '<tr>';
        cols.forEach(function(c) {
            var val = r[c] || '-';
            if (c === 'delivery_date' && cid) {
                var rawDate = r._delivery_date_raw || '';
                html += '<td><input type="date" class="delivery-input dp-date" data-cid="' + cid + '" value="' + rawDate + '"></td>';
            } else if (c === 'delivery_time' && cid) {
                var rawTime = r._delivery_time_raw || '';
                html += '<td><input type="time" class="delivery-input dp-time" data-cid="' + cid + '" value="' + rawTime + '"></td>';
            } else {
                var isDate = c === 'eta_local' || c === 'last_free_day';
                html += '<td' + (isDate ? ' class="font-mono small"' : '') + '>' + val + '</td>';
            }
        });
        html += '</tr>';
        return html;
    }

    // ─── Save Single Delivery (AJAX) ───
    function saveSingleDelivery(containerId) {
        var dateInput = document.querySelector('.dp-date[data-cid="' + containerId + '"]');
        var timeInput = document.querySelector('.dp-time[data-cid="' + containerId + '"]');
        var date = dateInput ? dateInput.value : '';
        var time = timeInput ? timeInput.value : '';

        var fd = new FormData();
        fd.append('container_id', containerId);
        fd.append('delivery_date', date);
        fd.append('delivery_time', time);

        fetch('?page=delivery-planner/save', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    var cb = document.querySelector('.planner-container-cb[value="' + containerId + '"]');
                    if (cb) {
                        var formCheck = cb.closest('.form-check');
                        if (formCheck) {
                            formCheck.setAttribute('data-has-plan', date || time ? '1' : '0');
                        }
                    }
                }
            })
            .catch(function() {});
    }

    // ─── Copy as HTML ───
    window.copyAsHtml = function() {
        var data = window._lastPreviewData;
        if (!data || !data.htmlTable) {
            showToast('Refresh the preview first.', 'warning');
            return;
        }
        var html = data.htmlTable;
        var includeDel = document.getElementById('includeDelivery').checked;
        if (!includeDel) {
            // Remove last 2 columns from the HTML table
            html = html.replace(/<th[^>]*>Delivery Date<\/th>\s*<th[^>]*>Delivery Time<\/th>/i, '');
            html = html.replace(/<td[^>]*>.*?<\/td>\s*<td[^>]*>.*?<\/td>/g, function(match, offset, str) {
                return '';
            });
        }
        if (!document.getElementById('includeHeaders').checked) {
            html = html.replace(/<thead>[\s\S]*?<\/thead>/, '');
        }
        copyToClipboard(html, 'text/html');
    };

    // ─── Copy as Text ───
    window.copyAsText = function() {
        var data = window._lastPreviewData;
        if (!data || !data.rows) {
            showToast('Refresh the preview first.', 'warning');
            return;
        }
        var includeHeaders = document.getElementById('includeHeaders').checked;
        var includeDel = document.getElementById('includeDelivery').checked;
        var lines = [];

        var headers = data.headers;
        var shownHeaders = includeDel ? headers : headers.slice(0, 3);

        if (includeHeaders) {
            lines.push(shownHeaders.join('\t'));
        }

        data.rows.forEach(function(r) {
            var vals = [];
            if (includeDel) {
                vals = [stripHtml(r.container_number || '-'), r.eta_local || '-', r.last_free_day || '-', r._delivery_date_raw || '', r._delivery_time_raw || ''];
            } else {
                vals = [stripHtml(r.container_number || '-'), r.eta_local || '-', r.last_free_day || '-'];
            }
            lines.push(vals.join('\t'));
        });

        copyToClipboard(lines.join('\n'), 'text/plain');
    };

    function stripHtml(str) {
        var div = document.createElement('div');
        div.innerHTML = str;
        return div.textContent;
    }

    function copyToClipboard(content, mimeType) {
        if (mimeType === 'text/html' && navigator.clipboard.write) {
            var blob = new Blob([content], { type: 'text/html' });
            navigator.clipboard.write([
                new ClipboardItem({ 'text/html': blob, 'text/plain': new Blob([stripHtml(content)], { type: 'text/plain' }) })
            ]).then(function() {
                showToast('HTML table copied!', 'success');
            }).catch(function() { fallbackCopy(content); });
        } else {
            navigator.clipboard.writeText(content).then(function() {
                showToast('Text copied!', 'success');
            }).catch(function() { fallbackCopy(content); });
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showToast('Copied!', 'success');
        } catch (e) {
            showToast('Failed to copy.', 'error');
        }
        document.body.removeChild(ta);
    }

    // ─── Toast Notification ───
    function showToast(msg, type) {
        var toast = document.getElementById('copyToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast-copied';
            toast.id = 'copyToast';
            document.body.appendChild(toast);
        }
        var icon = type === 'success' ? 'fa-check-circle text-white' : (type === 'warning' ? 'fa-exclamation-circle text-warning' : 'fa-times-circle text-white');
        toast.innerHTML = '<i class="fas ' + icon + ' me-1"></i>' + msg;
        toast.style.background = type === 'success' ? '#198754' : (type === 'warning' ? '#ffc107' : '#dc3545');
        toast.classList.add('show');
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(function() { toast.classList.remove('show'); }, 2500);
    }

    // ─── Initial load ───
    setTimeout(refreshPreview, 500);
})();
