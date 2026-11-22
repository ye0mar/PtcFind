<?php
/**
 * View Single Lost Item
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . base_url('lost_items/index.php'));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT l.*, c.name AS category_name, u.full_name AS reporter_name, u.email AS reporter_email
     FROM lost_items l
     LEFT JOIN categories c ON l.category_id = c.id
     LEFT JOIN users u ON l.reported_by = u.id
     WHERE l.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Lost item record not found.');
    header('Location: ' . base_url('lost_items/index.php'));
    exit;
}

// Find potential matching found items (same category, available status)
$matchesStmt = $pdo->prepare(
    "SELECT f.*, c.name AS category_name
     FROM found_items f
     LEFT JOIN categories c ON f.category_id = c.id
     WHERE f.category_id = :cat AND f.status = 'available'
     ORDER BY f.created_at DESC, f.id DESC
     LIMIT 5"
);
$matchesStmt->execute([':cat' => $item['category_id']]);
$potentialMatches = $matchesStmt->fetchAll();

// Check if already matched
$existingMatchStmt = $pdo->prepare(
    "SELECT m.*, f.item_name AS found_name, f.storage_location, u.full_name AS confirmed_by_name
     FROM matches m
     JOIN found_items f ON m.found_item_id = f.id
     LEFT JOIN users u ON m.confirmed_by = u.id
     WHERE m.lost_item_id = :lid
     LIMIT 1"
);
$existingMatchStmt->execute([':lid' => $id]);
$existingMatch = $existingMatchStmt->fetch();

ensure_returned_items_table($pdo);
$returnStmt = $pdo->prepare(
    "SELECT id, receiver_name, date_returned
     FROM returned_items
     WHERE lost_item_id = :lid" . ($existingMatch ? " OR match_id = :mid" : "") . "
     ORDER BY id DESC LIMIT 1"
);
$returnParams = [':lid' => $id];
if ($existingMatch) {
    $returnParams[':mid'] = (int)$existingMatch['id'];
}
$returnStmt->execute($returnParams);
$returnedRecord = $returnStmt->fetch();

$pageTitle = 'View Lost Item: ' . $item['item_name'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 1050px;">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                <div>
                    <a href="<?= base_url('lost_items/index.php') ?>" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-arrow-left"></i> Back to Lost Items
                    </a>
                    <h1 class="h3 fw-semibold mb-2"><?= sanitize($item['item_name']) ?></h1>
                    <span class="badge status-badge-<?= sanitize($item['status']) ?>">
                        STATUS: <?= strtoupper(sanitize($item['status'])) ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($returnedRecord): ?>
                        <a href="<?= base_url('returned_items/view.php?id=' . (int)$returnedRecord['id']) ?>" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-clipboard-check"></i> View Return Log
                        </a>
                    <?php elseif ($item['status'] !== 'returned'): ?>
                        <a href="<?= base_url('returned_items/add.php?' . ($existingMatch ? ('match_id=' . (int)$existingMatch['id']) : ('lost_item_id=' . (int)$item['id']))) ?>" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-clipboard-check"></i> Record Return
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('lost_items/edit.php?id=' . $item['id']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </a>
                    <form method="POST" action="<?= base_url('lost_items/process.php') ?>" data-confirm="Delete this lost item record?">
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

            <?php if ($returnedRecord): ?>
                <div class="alert alert-success p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-success" style="width: 46px; height: 46px;">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <div>
                                <h5 class="font-display fw-semibold mb-0">Item Returned to Owner</h5>
                                <p class="font-meta small mb-0">
                                    Received by <strong><?= sanitize($returnedRecord['receiver_name']) ?></strong> on <?= format_date($returnedRecord['date_returned']) ?>.
                                </p>
                            </div>
                        </div>
                        <a href="<?= base_url('returned_items/view.php?id=' . (int)$returnedRecord['id']) ?>" class="btn btn-neutral btn-sm px-3">View Return Proof</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Existing Match Banner -->
            <?php if ($existingMatch): ?>
                <div class="alert alert-success p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-primary" style="width: 46px; height: 46px;">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div>
                                <h5 class="font-display fw-semibold mb-0">Matched with Found Item</h5>
                                <p class="font-meta small mb-0">
                                    Linked with: <strong><?= sanitize($existingMatch['found_name']) ?></strong> (Storage: <?= sanitize($existingMatch['storage_location']) ?>)
                                    &middot; Confirmed by <?= sanitize($existingMatch['confirmed_by_name'] ?? 'Staff') ?>
                                </p>
                            </div>
                        </div>
                        <a href="<?= base_url('matches/index.php') ?>" class="btn btn-neutral btn-sm px-3">View Match Details</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Left Details Column -->
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
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Date Lost</small>
                                    <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-calendar-days me-1 text-primary"></i><?= format_date($item['date_lost']) ?></div>
                                </div>
                                <div class="col-12">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Location Lost</small>
                                    <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-location-dot me-1 text-secondary"></i><?= sanitize($item['location_lost']) ?></div>
                                </div>
                            </div>

                            <h6 class="fw-semibold text-dark mb-2">Description</h6>
                            <div class="p-3 bg-light rounded-3 text-secondary mb-4 border">
                                <?= nl2br(sanitize($item['description'])) ?>
                            </div>

                            <h6 class="fw-semibold text-dark mb-2">Owner Contact Information</h6>
                            <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-2 text-dark border">
                                <i class="fa-solid fa-phone text-primary"></i>
                                <span class="font-meta fw-semibold"><?= sanitize($item['contact_info']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Potential Found Matches suggestion box -->
                    <?php if ($item['status'] === 'open'): ?>
                        <div class="card overflow-hidden">
                            <div class="card-header py-3 px-4">
                                <h5 class="font-display fw-semibold mb-0 d-flex align-items-center gap-2 fs-6">
                                    <i class="fa-solid fa-link text-primary"></i> Potential Found Matches in Storage
                                </h5>
                                <small class="font-meta text-muted">Available items registered in category: <?= sanitize($item['category_name']) ?></small>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($potentialMatches)): ?>
                                    <div class="p-4 text-center font-meta text-muted small">
                                        No matching items currently available in storage.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($potentialMatches as $found): ?>
                                            <div class="list-group-item p-3 d-flex align-items-center justify-content-between gap-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="stat-icon-wrapper stat-icon-success" style="width: 42px; height: 42px; font-size: 1rem;">
                                                        <i class="fa-solid fa-box-open"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-semibold text-dark mb-0"><?= sanitize($found['item_name']) ?></h6>
                                                        <small class="font-meta text-muted">Found on <?= format_date($found['date_found']) ?> &middot; Storage: <?= sanitize($found['storage_location']) ?></small>
                                                    </div>
                                                </div>
                                                <form method="POST" action="<?= base_url('matches/process.php') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="create">
                                                    <input type="hidden" name="lost_item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="found_item_id" value="<?= $found['id'] ?>">
                                                    <input type="hidden" name="notes" value="Matched automatically via item view recommendation.">
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

                <!-- Right Column (Image & Meta) -->
                <div class="col-12 col-lg-5">
                    <div class="card mb-4 overflow-hidden">
                        <?php if (!empty($item['image_path']) && item_image_exists($item['image_path'])): ?>
                            <img src="<?= base_url($item['image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-detail-image" data-fallback-kind="detail" data-fallback-icon="fa-regular fa-image" data-fallback-text="No image uploaded for this item">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center">
                                <i class="fa-regular fa-image fs-1 d-block mb-2 text-secondary"></i>
                                <span class="font-meta small">No image uploaded for this item</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-body p-4">
                            <h6 class="font-display fw-semibold text-dark mb-3">Audit Details</h6>
                            <ul class="list-unstyled mb-0 small font-meta text-muted">
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Record ID</span>
                                    <span class="fw-bold text-dark">#<?= $item['id'] ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Reported By</span>
                                    <span class="fw-semibold text-dark"><?= sanitize($item['reporter_name'] ?? 'Staff') ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Created At</span>
                                    <span><?= format_datetime($item['created_at']) ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2">
                                    <span>Last Updated</span>
                                    <span><?= format_datetime($item['updated_at'] ?? $item['created_at']) ?></span>
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
