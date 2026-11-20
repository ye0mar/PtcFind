<?php
/**
 * View Single Found Item
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . base_url('found_items/index.php'));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT f.*, c.name AS category_name, u.full_name AS recorder_name
     FROM found_items f
     LEFT JOIN categories c ON f.category_id = c.id
     LEFT JOIN users u ON f.recorded_by = u.id
     WHERE f.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Found item record not found.');
    header('Location: ' . base_url('found_items/index.php'));
    exit;
}

// Find open lost items in the same category
$lostMatchesStmt = $pdo->prepare(
    "SELECT l.*, c.name AS category_name, u.full_name AS reporter_name
     FROM lost_items l
     LEFT JOIN categories c ON l.category_id = c.id
     LEFT JOIN users u ON l.reported_by = u.id
     WHERE l.category_id = :cat AND l.status = 'open'
     ORDER BY l.created_at DESC
     LIMIT 5"
);
$lostMatchesStmt->execute([':cat' => $item['category_id']]);
$openLostItems = $lostMatchesStmt->fetchAll();

// Check if already matched
$existingMatchStmt = $pdo->prepare(
    "SELECT m.*, l.item_name AS lost_name, l.contact_info, u.full_name AS confirmed_by_name
     FROM matches m
     JOIN lost_items l ON m.lost_item_id = l.id
     LEFT JOIN users u ON m.confirmed_by = u.id
     WHERE m.found_item_id = :fid
     LIMIT 1"
);
$existingMatchStmt->execute([':fid' => $id]);
$existingMatch = $existingMatchStmt->fetch();

$pageTitle = 'View Found Item: ' . $item['item_name'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 1050px;">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                <div>
                    <a href="<?= base_url('found_items/index.php') ?>" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-arrow-left"></i> Back to Found Inventory
                    </a>
                    <h1 class="h3 fw-semibold mb-2"><?= sanitize($item['item_name']) ?></h1>
                    <span class="badge status-badge-<?= sanitize($item['status']) ?>">
                        STATUS: <?= strtoupper(sanitize($item['status'])) ?>
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('found_items/edit.php?id=' . $item['id']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </a>
                    <form method="POST" action="<?= base_url('found_items/process.php') ?>" onsubmit="return confirm('Delete this found item record?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>

            <?= render_flash() ?>

            <!-- Existing Match Alert -->
            <?php if ($existingMatch): ?>
                <div class="alert alert-success p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-primary" style="width: 46px; height: 46px;">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div>
                                <h5 class="font-display fw-semibold mb-0">Matched with Lost Item</h5>
                                <p class="font-meta small mb-0">
                                    Linked with lost report: <strong><?= sanitize($existingMatch['lost_name']) ?></strong>
                                    (Owner contact: <?= sanitize($existingMatch['contact_info']) ?>)
                                    &middot; Confirmed by <?= sanitize($existingMatch['confirmed_by_name'] ?? 'Staff') ?>
                                </p>
                            </div>
                        </div>
                        <a href="<?= base_url('matches/index.php') ?>" class="btn btn-neutral btn-sm px-3">View Match Details</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Left Details -->
                <div class="col-12 col-lg-7">
                    <div class="card mb-4">
                        <div class="card-body p-4">
                            <h5 class="font-display fw-semibold mb-3">Item Specifications</h5>

                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Category</small>
                                    <div class="fw-semibold text-dark mt-1"><?= sanitize($item['category_name'] ?? 'General') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Date Found</small>
                                    <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-calendar-days me-1 text-primary"></i><?= format_date($item['date_found']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Location Found</small>
                                    <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-location-dot me-1 text-secondary"></i><?= sanitize($item['location_found']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Storage Locker / Custody</small>
                                    <div class="fw-semibold text-primary mt-1"><i class="fa-solid fa-box-archive me-1"></i><?= sanitize($item['storage_location']) ?></div>
                                </div>
                            </div>

                            <h6 class="fw-semibold text-dark mb-2">Description</h6>
                            <div class="p-3 bg-light rounded-3 text-secondary mb-4 border">
                                <?= nl2br(sanitize($item['description'])) ?>
                            </div>

                            <?php if (!empty($item['notes'])): ?>
                                <h6 class="fw-semibold text-dark mb-2">Internal Staff Custody Notes</h6>
                                <div class="p-3 bg-light rounded-3 text-secondary small border">
                                    <i class="fa-solid fa-note-sticky me-1 text-primary"></i> <?= nl2br(sanitize($item['notes'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Potential Lost Reports Match List -->
                    <?php if ($item['status'] === 'available'): ?>
                        <div class="card overflow-hidden">
                            <div class="card-header py-3 px-4">
                                <h5 class="font-display fw-semibold mb-0 d-flex align-items-center gap-2 fs-6">
                                    <i class="fa-solid fa-link text-primary"></i> Potential Open Lost Reports
                                </h5>
                                <small class="font-meta text-muted">Open reports registered under category: <?= sanitize($item['category_name']) ?></small>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($openLostItems)): ?>
                                    <div class="p-4 text-center font-meta text-muted small">
                                        No open lost reports currently match this item's category.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($openLostItems as $lost): ?>
                                            <div class="list-group-item p-3 d-flex align-items-center justify-content-between gap-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="stat-icon-wrapper stat-icon-warning" style="width: 42px; height: 42px; font-size: 1rem;">
                                                        <i class="fa-solid fa-circle-question"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-semibold text-dark mb-0"><?= sanitize($lost['item_name']) ?></h6>
                                                        <small class="font-meta text-muted">Lost on <?= format_date($lost['date_lost']) ?> at <?= sanitize($lost['location_lost']) ?></small>
                                                    </div>
                                                </div>
                                                <form method="POST" action="<?= base_url('matches/process.php') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="create">
                                                    <input type="hidden" name="lost_item_id" value="<?= $lost['id'] ?>">
                                                    <input type="hidden" name="found_item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="notes" value="Matched via found item inspection.">
                                                    <button type="submit" class="btn btn-sm btn-success text-nowrap">
                                                        <i class="fa-solid fa-link"></i> Link &amp; Match
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Meta -->
                <div class="col-12 col-lg-5">
                    <div class="card mb-4 overflow-hidden">
                        <?php if (!empty($item['image_path']) && item_image_exists($item['image_path'])): ?>
                            <img src="<?= base_url($item['image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-detail-image" data-fallback-kind="detail" data-fallback-icon="fa-solid fa-box-open" data-fallback-text="No photo uploaded for this item">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center">
                                <i class="fa-solid fa-box-open fs-1 d-block mb-2 text-secondary"></i>
                                <span class="font-meta small">No photo uploaded for this item</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-body p-4">
                            <h6 class="font-display fw-semibold text-dark mb-3">Custody Information</h6>
                            <ul class="list-unstyled mb-0 small font-meta text-muted">
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Inventory ID</span>
                                    <span class="fw-bold text-dark">#<?= $item['id'] ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Recorded By</span>
                                    <span class="fw-semibold text-dark"><?= sanitize($item['recorder_name'] ?? 'Staff') ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Recorded On</span>
                                    <span><?= format_datetime($item['created_at']) ?></span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center py-2">
                                    <span>Storage Location</span>
                                    <span class="storage-badge"><?= sanitize($item['storage_location']) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
