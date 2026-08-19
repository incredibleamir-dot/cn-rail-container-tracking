<?php $pageTitle = 'Admin - API Settings'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-cog me-2 text-primary"></i>API Settings</h4><small class="text-muted">Configure CN Rail API credentials</small></div>
    <a href="/admin" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card-modern p-4">
            <h6 class="fw-bold mb-3"><i class="fas fa-database me-2 text-primary"></i>Data Source</h6>
            <form method="POST" action="/admin/settings/save">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Tracking Data Source</label>
                    <select name="data_source" class="form-select" id="dataSourceSelect">
                        <option value="cn_api" <?php echo ($settings['data_source'] ?? 'cn_api') === 'cn_api' ? 'selected' : ''; ?>>CN Rail API (Live)</option>
                        <option value="google_sheet" <?php echo ($settings['data_source'] ?? '') === 'google_sheet' ? 'selected' : ''; ?>>Google Sheet</option>
                    </select>
                </div>
                <div class="mb-3" id="gasUrlGroup" style="<?php echo ($settings['data_source'] ?? '') !== 'google_sheet' ? 'display:none' : ''; ?>">
                    <label class="form-label fw-bold small">Google Apps Script Web App URL</label>
                    <input type="url" name="gas_url" class="form-control" value="<?php echo htmlspecialchars($settings['gas_url'] ?? ''); ?>" placeholder="https://script.google.com/macros/s/.../exec">
                </div>
                <hr>
                <h6 class="fw-bold mb-3"><i class="fas fa-key me-2 text-primary"></i>CN Rail API Credentials</h6>
                <div class="mb-3"><label class="form-label fw-bold small">API Key (x-apikey)</label><input type="text" name="cn_api_key" class="form-control font-monospace" value="<?php echo htmlspecialchars($settings['cn_api_key'] ?? ''); ?>"></div>
                <div class="mb-3"><label class="form-label fw-bold small">Auth Key (client secret)</label><input type="password" name="cn_auth_key" class="form-control font-monospace" value="<?php echo htmlspecialchars($settings['cn_auth_key'] ?? ''); ?>"></div>
                <div class="mb-4">
                    <label class="form-label fw-bold small">Timezone</label>
                    <select name="timezone" class="form-select">
                        <option value="America/Toronto" <?php echo ($settings['timezone'] ?? '') === 'America/Toronto' ? 'selected' : ''; ?>>Eastern (ET)</option>
                        <option value="America/Winnipeg" <?php echo ($settings['timezone'] ?? '') === 'America/Winnipeg' ? 'selected' : ''; ?>>Central (CT)</option>
                        <option value="America/Edmonton" <?php echo ($settings['timezone'] ?? '') === 'America/Edmonton' ? 'selected' : ''; ?>>Mountain (MT)</option>
                        <option value="America/Vancouver" <?php echo ($settings['timezone'] ?? '') === 'America/Vancouver' ? 'selected' : ''; ?>>Pacific (PT)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Settings</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-modern p-4">
            <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Data Source Info</h6>
            <div id="infoApi" style="<?php echo ($settings['data_source'] ?? 'cn_api') === 'google_sheet' ? 'display:none' : ''; ?>">
                <p class="small text-muted">CN Rail API endpoint: <code>https://api.cn.ca/customers/v1/shipments/tracking</code></p>
                <p class="small text-muted">Auth endpoint: <code>https://api.cn.ca/v1/oauth/jwt-token/accesstokenJWT</code></p>
            </div>
            <div id="infoSheet" style="<?php echo ($settings['data_source'] ?? '') !== 'google_sheet' ? 'display:none' : ''; ?>">
                <p class="small text-muted">Uses a Google Apps Script web app to read from your Google Sheet.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('dataSourceSelect').addEventListener('change', function() {
    var isSheet = this.value === 'google_sheet';
    document.getElementById('gasUrlGroup').style.display = isSheet ? '' : 'none';
    document.getElementById('infoApi').style.display = isSheet ? 'none' : '';
    document.getElementById('infoSheet').style.display = isSheet ? '' : 'none';
});
</script>
