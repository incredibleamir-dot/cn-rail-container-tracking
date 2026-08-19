<?php
$currentPage = $currentPage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> | <?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/colreorder/2.0.3/css/colReorder.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-train"></i>
            <span><?php echo APP_NAME; ?></span>
        </div>
        <ul class="sidebar-nav">
            <?php
            $isArchived = $showArchived ?? false;
            $navItems = [
                ['url' => '/', 'icon' => 'fa-th-large', 'label' => 'Dashboard', 'match' => ['/']],
                ['url' => '/shipments', 'icon' => 'fa-ship', 'label' => 'Shipments', 'match' => ['/shipments']],
                ['url' => '/containers', 'icon' => 'fa-list', 'label' => 'Containers', 'match' => ['/containers']],
                ['url' => '/quick-track', 'icon' => 'fa-search', 'label' => 'Quick Track', 'match' => ['/quick-track']],
                ['url' => '/delivery-planner', 'icon' => 'fa-calendar-alt', 'label' => 'Delivery Planner', 'match' => ['/delivery-planner']],
                ['url' => '/analysis', 'icon' => 'fa-chart-pie', 'label' => 'Analysis', 'match' => ['/analysis']],
            ];
            if (($_SESSION['role'] ?? '') === 'admin') {
                $navItems[] = ['url' => '/admin', 'icon' => 'fa-user-shield', 'label' => 'Admin', 'match' => ['/admin', '/admin/settings']];
            }

            foreach ($navItems as $nav) {
                $isActive = in_array($currentPage, $nav['match'] ?? []);
            ?>
            <li>
                <a href="<?php echo $nav['url']; ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                    <i class="fas <?php echo $nav['icon']; ?>"></i>
                    <span><?php echo $nav['label']; ?></span>
                </a>
            </li>
            <?php } ?>
        </ul>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
            </div>
            <a href="/logout" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-wrapper">
        <header class="topbar">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border py-2 px-3 small auto-refresh-timer" id="refreshTimer">
                    <i class="fas fa-clock me-1"></i><span id="refreshCountdown">10:00</span>
                </span>
            </div>
        </header>

        <?php if (DEBUG_MODE): ?>
            <?php \Debug::renderBanner(); ?>
        <?php endif; ?>

        <main class="main-content">
            <?php
            // Flash messages
            if (!empty($flashSuccess)): ?>
                <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn mb-4">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($flashSuccess); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif;
            if (!empty($flashError)): ?>
                <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($flashError); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif;

            // Render content view
            require __DIR__ . '/../' . $contentView . '.php';
            ?>
        </main>

        <footer class="footer">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="text-muted small">
                        <i class="fas fa-satellite-dish me-2 text-primary"></i>Tracking data from CN Rail API
                    </div>
                    <div class="text-end small">
                        <span class="text-muted">Designed by <strong>Amir Arshad</strong> at <span class="text-primary fw-bold">Avancer International Freight System Inc</span> &copy; <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none flex-column justify-content-center align-items-center">
        <div class="satellite-pulse mb-4"><i class="fas fa-satellite-dish"></i></div>
        <h5 class="fw-bold text-dark mb-1"><?php echo APP_NAME; ?></h5>
        <div class="loading-steps text-primary" id="loadingText">Connecting...</div>
    </div>

    <!-- Edit Metadata Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h6 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Container Details</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="container_id" id="edit_container_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Bill of Lading #</label>
                                <input type="text" name="bill_of_lading" id="edit_bol" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">PO #</label>
                                <input type="text" name="po_number" id="edit_po" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Customer / Shipper</label>
                                <input type="text" name="customer_name" id="edit_customer" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Destination</label>
                                <input type="text" name="destination" id="edit_destination" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small">Commodity</label>
                                <input type="text" name="commodity" id="edit_commodity" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small">Notes</label>
                                <textarea name="notes" id="edit_notes" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small">Tags</label>
                                <input type="text" name="tags" id="edit_tags" class="form-control form-control-sm" placeholder="Comma separated: urgent, delayed">
                            </div>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/colreorder/2.0.3/js/dataTables.colReorder.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <?php \Debug::renderPanel(); ?>
</body>
</html>