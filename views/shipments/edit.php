<?php $pageTitle = 'Edit Shipment'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="fw-bold mb-0"><i class="fas fa-edit me-2 text-primary"></i>Edit Shipment</h4></div>
    <a href="/shipments/view?id=<?php echo $shipment['id']; ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card-modern p-4">
    <form method="POST" action="/shipments/edit?id=<?php echo $shipment['id']; ?>">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-bold small">Shipment Title</label><input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['title']); ?>"></div>
            <div class="col-md-6"><label class="form-label fw-bold small">Bill of Lading #</label><input type="text" name="bill_of_lading" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['bill_of_lading']); ?>"></div>
            <div class="col-md-4"><label class="form-label fw-bold small">PO #</label><input type="text" name="po_number" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['po_number']); ?>"></div>
            <div class="col-md-4"><label class="form-label fw-bold small">Customer / Shipper</label><input type="text" name="customer_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['customer_name']); ?>"></div>
            <div class="col-md-4"><label class="form-label fw-bold small">Destination</label><input type="text" name="destination" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['destination']); ?>"></div>
            <div class="col-md-6"><label class="form-label fw-bold small">Commodity</label><input type="text" name="commodity" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['commodity']); ?>"></div>
            <div class="col-md-6"><label class="form-label fw-bold small">Tags</label><input type="text" name="tags" class="form-control form-control-sm" value="<?php echo htmlspecialchars($shipment['tags']); ?>"></div>
            <div class="col-12"><label class="form-label fw-bold small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($shipment['notes']); ?></textarea></div>
            <div class="col-12 text-end"><a href="/shipments/view?id=<?php echo $shipment['id']; ?>" class="btn btn-light">Cancel</a><button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i>Save</button></div>
        </div>
    </form>
</div>
