<?php $pageTitle = 'Create Shipment'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-ship me-2 text-primary"></i>Create Shipment</h4><small class="text-muted">Enter shipment details, then add container numbers below.</small></div>
    <a href="/shipments" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card-modern p-4">
    <form method="POST" action="/shipments/create">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-bold small">Shipment Title <span class="text-muted">(optional)</span></label><input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Q4 Order #1142"></div>
            <div class="col-md-6"><label class="form-label fw-bold small">Bill of Lading #</label><input type="text" name="bill_of_lading" class="form-control form-control-sm"></div>
            <div class="col-md-4"><label class="form-label fw-bold small">PO #</label><input type="text" name="po_number" class="form-control form-control-sm"></div>
            <div class="col-md-4"><label class="form-label fw-bold small">Customer / Shipper</label><input type="text" name="customer_name" class="form-control form-control-sm"></div>
            <div class="col-md-4"><label class="form-label fw-bold small">Destination</label><input type="text" name="destination" class="form-control form-control-sm"></div>
            <div class="col-md-6"><label class="form-label fw-bold small">Commodity</label><input type="text" name="commodity" class="form-control form-control-sm"></div>
            <div class="col-md-6"><label class="form-label fw-bold small">Tags</label><input type="text" name="tags" class="form-control form-control-sm" placeholder="Comma separated"></div>
            <div class="col-12"><label class="form-label fw-bold small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
            <div class="col-12"><hr><label class="form-label fw-bold small"><i class="fas fa-boxes me-1"></i>Add Container Numbers</label><textarea name="containers" class="form-control font-mono" rows="3" placeholder="CNRU123456&#10;MRSU654321, TCLU111111"></textarea><div class="text-muted small mt-1">Paste one per line or comma/space separated.</div></div>
            <div class="col-12 text-end"><a href="/shipments" class="btn btn-light">Cancel</a><button type="submit" class="btn btn-primary px-4"><i class="fas fa-plus me-1"></i>Create Shipment</button></div>
        </div>
    </form>
</div>
