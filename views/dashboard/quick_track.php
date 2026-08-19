<?php $pageTitle = 'Quick Track'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-search me-2 text-primary"></i>Quick Track</h4>
        <small class="text-muted">Look up a container's current status without adding it to your watchlist.</small>
    </div>
    <a href="/" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-modern p-4">
            <h6 class="fw-bold mb-3"><i class="fas fa-keyboard me-2 text-primary"></i>Container Number</h6>
            <form id="quickTrackForm">
                <div class="mb-3">
                    <input type="text" id="qtInput" class="form-control form-control-lg font-mono" placeholder="CNRU1234567" required style="text-transform:uppercase;letter-spacing:1px;" maxlength="11" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')">
                    <div class="form-text small text-muted">Enter 4 letters + 7 digits (e.g. CNRU1234567)</div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2" id="qtBtn"><i class="fas fa-satellite-dish me-1"></i>Track</button>
            </form>
            <div id="qtError" class="alert alert-danger d-none mt-3 py-2 small"></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-modern p-4" id="qtResult" style="display:none;">
            <h6 class="fw-bold mb-3"><i class="fas fa-box me-2 text-primary"></i><span id="qtContainerNumber"></span></h6>
            <div class="row g-3" id="qtData"></div>
        </div>
        <div class="card-modern p-4 text-center text-muted" id="qtPlaceholder">
            <i class="fas fa-search fa-3x opacity-25 mb-3"></i>
            <p class="mb-0">Enter a container number and click Track to see its current status.</p>
        </div>
    </div>
</div>

<script>
document.getElementById('quickTrackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var input = document.getElementById('qtInput');
    var btn = document.getElementById('qtBtn');
    var error = document.getElementById('qtError');
    var result = document.getElementById('qtResult');
    var placeholder = document.getElementById('qtPlaceholder');
    var num = input.value.trim();
    if (num.length < 10) { error.textContent = 'Please enter a valid container number (4 letters + 7 digits).'; error.classList.remove('d-none'); return; }
    error.classList.add('d-none');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Tracking...';
    result.style.display = 'none';
    var fd = new FormData();
    fd.append('container_number', num);
    fetch('/quick-track/lookup', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-satellite-dish me-1"></i>Track';
            placeholder.style.display = 'none';
            if (!data.success) { error.textContent = data.error || 'Lookup failed.'; error.classList.remove('d-none'); return; }
            result.style.display = 'block';
            document.getElementById('qtContainerNumber').textContent = data.data.container || num;
            var d = data.data;
            var html = '';
            var fields = {
                'Status': d.waybillStatus || '-',
                'State': d.loadEmpty === 'L' ? 'Loaded' : (d.loadEmpty === 'E' ? 'Empty' : (d.loadEmpty || '-')),
                'Last Event': d.lastEvent || '-',
                'Location': d.lastEventLocation || '-',
                'Event Time': d.lastEventTimeLocal ? new Date(d.lastEventTimeLocal).toLocaleString('en-US', {month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-',
                'ETA Station': d.etaStation || '-',
                'ETA': d.etaLocal ? new Date(d.etaLocal).toLocaleString('en-US', {month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : '-',
                'Last Free Day': d.lastFreeDay || '-',
            };
            for (var label in fields) { html += '<div class="col-md-6"><div class="detail-label">' + label + '</div><div class="detail-value">' + fields[label] + '</div></div>'; }
            document.getElementById('qtData').innerHTML = html;
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-satellite-dish me-1"></i>Track'; error.textContent = 'Network error. Please try again.'; error.classList.remove('d-none'); });
});
</script>
