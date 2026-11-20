<?php
/**
 * Main Management Dashboard
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Dashboard';
$extraCss = ['assets/css/dashboard.css'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$pdo = get_db_connection();
$user = current_user();

// Fetch summary metrics
try {
    $countLostOpen = $pdo->query("SELECT COUNT(*) FROM lost_items WHERE status = 'open'")->fetchColumn() ?: 0;
    $countFoundAvail = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'available'")->fetchColumn() ?: 0;
    $countMatches = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn() ?: 0;
    $countUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn() ?: 0;

    // Recent 5 lost items
    $stmtLost = $pdo->query(
        "SELECT l.*, c.name AS category_name, u.full_name AS reporter_name
         FROM lost_items l
         LEFT JOIN categories c ON l.category_id = c.id
         LEFT JOIN users u ON l.reported_by = u.id
         ORDER BY l.created_at DESC
         LIMIT 5"
    );
    $recentLost = $stmtLost->fetchAll();

    // Recent 5 found items
    $stmtFound = $pdo->query(
        "SELECT f.*, c.name AS category_name, u.full_name AS recorder_name
         FROM found_items f
         LEFT JOIN categories c ON f.category_id = c.id
         LEFT JOIN users u ON f.recorded_by = u.id
         ORDER BY f.created_at DESC
         LIMIT 5"
    );
    $recentFound = $stmtFound->fetchAll();
} catch (Exception $e) {
    $countLostOpen = $countFoundAvail = $countMatches = $countUsers = 0;
    $recentLost = [];
    $recentFound = [];
    $dbError = $e->getMessage();
}
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid">
            <!-- Welcome Header -->
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-semibold mb-1">Welcome back, <?= sanitize($user['full_name']) ?>!</h1>
                    <p class="font-meta text-muted small mb-0">Overview of campus lost &amp; found belongings, ongoing matches, and locker inventory.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= base_url('lost_items/report.php') ?>" class="btn btn-outline-primary btn-sm px-3 py-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>Report Lost</span>
                    </a>
                    <a href="<?= base_url('found_items/add.php') ?>" class="btn btn-primary btn-sm px-3 py-2">
                        <i class="fa-solid fa-box-open"></i>
                        <span>Record Found</span>
                    </a>
                </div>
            </div>

            <!-- Flash alerts -->
            <?= render_flash() ?>
            <?php if (isset($dbError)): ?>
                <div class="alert alert-warning">Database note: <?= sanitize($dbError) ?></div>
            <?php endif; ?>

            <!-- Stats KPI Cards -->
            <div class="row g-3 g-xl-4 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="stat-eyebrow">Open Lost Items</span>
                                <div class="stat-value stat-value-warning"><?= (int)$countLostOpen ?></div>
                                <span class="stat-subtext">Awaiting matches</span>
                            </div>
                            <div class="stat-icon-wrapper stat-icon-warning">
                                <i class="fa-solid fa-circle-question"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="stat-eyebrow">Available Found Items</span>
                                <div class="stat-value stat-value-success"><?= (int)$countFoundAvail ?></div>
                                <span class="stat-subtext">Stored in security lockers</span>
                            </div>
                            <div class="stat-icon-wrapper stat-icon-success">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="stat-eyebrow">Confirmed Matches</span>
                                <div class="stat-value stat-value-primary"><?= (int)$countMatches ?></div>
                                <span class="stat-subtext">Returned or linked</span>
                            </div>
                            <div class="stat-icon-wrapper stat-icon-primary">
                                <i class="fa-solid fa-link"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="stat-eyebrow">Active Operators</span>
                                <div class="stat-value stat-value-muted"><?= (int)$countUsers ?></div>
                                <span class="stat-subtext">Staff &amp; Administrators</span>
                            </div>
                            <div class="stat-icon-wrapper stat-icon-secondary">
                                <i class="fa-solid fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Items Tables Grid -->
            <div class="row g-4">
                <!-- Recent Lost Items -->
                <div class="col-12 col-lg-6">
                    <div class="card h-100 overflow-hidden">
                        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                            <h5 class="mb-0 d-flex align-items-center gap-2 fs-6">
                                <i class="fa-solid fa-circle-question text-primary"></i>
                                <span class="font-display">Recent Lost Items</span>
                            </h5>
                            <a href="<?= base_url('lost_items/index.php') ?>" class="btn btn-sm btn-neutral">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentLost)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fs-2"></i>
                                    <p class="small mt-2 mb-0">No lost items reported yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">Item</th>
                                                <th>Category</th>
                                                <th>Date Lost</th>
                                                <th>Status</th>
                                                <th class="text-end pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentLost as $item): ?>
                                                <tr>
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <?php if (!empty($item['image_path']) && item_image_exists($item['image_path'])): ?>
                                                                <img src="<?= base_url($item['image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-regular fa-image">
                                                            <?php else: ?>
                                                                <div class="item-thumb-placeholder">
                                                                    <i class="fa-regular fa-image"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-semibold text-dark"><?= sanitize($item['item_name']) ?></div>
                                                                <small class="font-meta text-muted"><?= sanitize($item['location_lost']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="category-badge"><?= sanitize($item['category_name'] ?? 'General') ?></span></td>
                                                    <td class="small font-meta text-muted text-nowrap"><?= format_date($item['date_lost']) ?></td>
                                                    <td>
                                                        <span class="badge status-badge-<?= sanitize($item['status']) ?>">
                                                            <?= strtoupper(sanitize($item['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <a href="<?= base_url('lost_items/view.php?id=' . $item['id']) ?>" class="btn btn-view btn-action-icon" title="View Details">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Found Items -->
                <div class="col-12 col-lg-6">
                    <div class="card h-100 overflow-hidden">
                        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                            <h5 class="mb-0 d-flex align-items-center gap-2 fs-6">
                                <i class="fa-solid fa-box-open text-primary"></i>
                                <span class="font-display">Recent Found Items</span>
                            </h5>
                            <a href="<?= base_url('found_items/index.php') ?>" class="btn btn-sm btn-neutral">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentFound)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fs-2"></i>
                                    <p class="small mt-2 mb-0">No found items registered yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">Item</th>
                                                <th>Category</th>
                                                <th>Storage</th>
                                                <th>Status</th>
                                                <th class="text-end pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentFound as $item): ?>
                                                <tr>
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <?php if (!empty($item['image_path']) && item_image_exists($item['image_path'])): ?>
                                                                <img src="<?= base_url($item['image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-solid fa-box-open">
                                                            <?php else: ?>
                                                                <div class="item-thumb-placeholder">
                                                                    <i class="fa-solid fa-box-open"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-semibold text-dark"><?= sanitize($item['item_name']) ?></div>
                                                                <small class="font-meta text-muted"><?= sanitize($item['location_found']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="category-badge"><?= sanitize($item['category_name'] ?? 'General') ?></span></td>
                                                    <td class="small font-meta text-muted text-nowrap">
                                                        <i class="fa-solid fa-location-dot me-1 text-secondary"></i><?= sanitize($item['storage_location'] ?? 'Unassigned') ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge status-badge-<?= sanitize($item['status']) ?>">
                                                            <?= strtoupper(sanitize($item['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <a href="<?= base_url('found_items/view.php?id=' . $item['id']) ?>" class="btn btn-view btn-action-icon" title="View Details">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
