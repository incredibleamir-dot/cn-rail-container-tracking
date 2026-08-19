/**
 * CN Track - Client-side JavaScript (Refactored)
 */

// Sidebar Toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// All init code runs after DOM is ready
document.addEventListener('DOMContentLoaded', function() {

    // Auto-Refresh Timer (10 min, persists across page reloads via localStorage)
    var timerEl = document.getElementById('refreshCountdown');
    if (timerEl) {
        var STORAGE_KEY = 'cntrack_refresh_expiry';
        var REFRESH_SECONDS = 600; // 10 minutes
        var stored = localStorage.getItem(STORAGE_KEY);
        var now = Date.now();
        var expiry;

        if (stored) {
            expiry = parseInt(stored, 10);
            // If expired or more than 11 minutes old (stale), reset
            if (isNaN(expiry) || expiry <= now || (now - expiry) > 660000) {
                expiry = now + REFRESH_SECONDS * 1000;
            }
        } else {
            expiry = now + REFRESH_SECONDS * 1000;
        }

        localStorage.setItem(STORAGE_KEY, expiry.toString());

        (function tick() {
            var remaining = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            timerEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;

            if (remaining <= 0) {
                // Timer expired - reload the page to refresh data
                localStorage.removeItem(STORAGE_KEY);
                location.reload();
            } else {
                setTimeout(tick, 1000);
            }
        })();
    }

    // Track Single Form (AJAX)
    document.querySelectorAll('.track-single-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            var fd = new FormData(this);
            fetch(this.action || '/track-single', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { location.reload(); })
            .catch(function() { location.reload(); });
        });
    });

    // Select All Checkbox
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = checked; });
        });
    }

    // Edit Metadata Form
    var editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            fetch('/api/save-metadata', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) location.reload();
                    else alert('Error: ' + (data.error || 'Unknown'));
                })
                .catch(function() { alert('Failed to save.'); });
        });
    }

    // Auto-hide flash messages
    setTimeout(function() {
        document.querySelectorAll('.alert-dismissible').forEach(function(a) {
            var btn = a.querySelector('.btn-close');
            if (btn) btn.click();
        });
    }, 5000);
});

// Share Selected Modal
function openShareModal() {
    var checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) { alert('Please select one or more containers using the checkboxes.'); return; }
    var tbody = document.getElementById('shareTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    checked.forEach(function(cb) {
        var row = cb.closest('tr');
        if (!row) return;
        var fields = ['container','shipment','status','state','event','location','eventtime','eta-station','eta','lfd','customs'];
        var tr = document.createElement('tr');
        fields.forEach(function(f) {
            var td = document.createElement('td');
            var val = row.getAttribute('data-' + f) || '-';
            td.innerHTML = f === 'container' ? val : val;
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    var now = new Date();
    var ts = 'As of ' + now.toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}) + ' ' + now.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
    var tsEl = document.getElementById('shareTimestamp');
    if (tsEl) tsEl.textContent = ts;
    new bootstrap.Modal(document.getElementById('shareModal')).show();
}

// Go to Delivery Planner with selected containers
function goToDeliveryPlanner() {
    var checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) { alert('Please select one or more containers first.'); return; }
    var ids = [];
    checked.forEach(function(cb) { ids.push(cb.value); });
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/delivery-planner';
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'container_ids';
    input.value = ids.join(',');
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Loading Animation
function startLoadingAnimation() {
    var overlay = document.getElementById('loadingOverlay');
    var text = document.getElementById('loadingText');
    if (!overlay || !text) return;
    var messages = ["Connecting to CN server...", "Connection successful", "Retrieving Information...", "Parsing data..."];
    overlay.classList.remove('d-none');
    overlay.classList.add('d-flex');
    text.innerText = messages[0];
    var step = 0;
    var interval = setInterval(function() {
        step++;
        if (step < messages.length) { text.innerText = messages[step]; } else { clearInterval(interval); }
    }, 800);
}

// Edit Metadata Modal
function openEditModal(containerId) {
    var cidField = document.getElementById('edit_container_id');
    if (cidField) cidField.value = containerId;
    ['edit_bol','edit_po','edit_customer','edit_destination','edit_commodity','edit_notes','edit_tags'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    fetch('/api/container?container_id=' + containerId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) return;
            var fields = { edit_bol: 'bill_of_lading', edit_po: 'po_number', edit_customer: 'customer_name', edit_destination: 'destination', edit_commodity: 'commodity', edit_notes: 'notes', edit_tags: 'tags' };
            Object.keys(fields).forEach(function(elId) {
                var el = document.getElementById(elId);
                if (el) el.value = data[fields[elId]] || '';
            });
        })
        .catch(function() {});
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Bulk Actions
function updateBulkActions() {
    var count = document.querySelectorAll('.row-check:checked').length;
    var bar = document.getElementById('bulkActions');
    if (bar) { bar.style.display = count > 0 ? 'block' : 'none'; var sc = document.getElementById('selectedCount'); if (sc) sc.innerText = count; }
}

function bulkArchive() {
    var ids = [];
    document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(cb.value); });
    if (!confirm('Archive ' + ids.length + ' container(s)?')) return;
    var promises = ids.map(function(id) {
        var fd = new FormData(); fd.append('container_id', id);
        return fetch('/archive', { method: 'POST', body: fd });
    });
    Promise.all(promises).then(function() { location.reload(); });
}

function refreshSingle(containerId) {
    var fd = new FormData(); fd.append('container_id', containerId);
    fetch('/track-single', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) { location.reload(); })
    .catch(function() { location.reload(); });
}

// Container Validation (client-side)
var LETTER_VALUES = {"A":10,"B":12,"C":13,"D":14,"E":15,"F":16,"G":17,"H":18,"I":19,"J":20,"K":21,"L":23,"M":24,"N":25,"O":26,"P":27,"Q":28,"R":29,"S":30,"T":31,"U":32,"V":34,"W":35,"X":36,"Y":37,"Z":38};

function validateContainer(number) {
    var regex = /^([A-Z]{4})(\d{7})$/;
    var match = number.match(regex);
    if (!match) return { valid: false, reason: "Format" };
    var prefix = match[1]; var serial = match[2]; var checkDigit = parseInt(serial[6]); var total = 0;
    for (var i = 0; i < 4; i++) { var val = LETTER_VALUES[prefix[i]]; if (!val) return { valid: false, reason: "Char" }; total += val * Math.pow(2, i); }
    for (var i = 0; i < 6; i++) { total += parseInt(serial[i]) * Math.pow(2, i + 4); }
    var cd = total % 11; if (cd === 10) cd = 0;
    return { valid: cd === checkDigit, reason: cd === checkDigit ? "" : "Check digit" };
}
