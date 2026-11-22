<?php
/**
 * View Single Returned Item Record
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
ensure_returned_items_table($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . base_url('returned_items/index.php'));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.*,
            c.name AS category_name,
            u.full_name AS recorder_name,
            l.item_name AS linked_lost_name,
            f.item_name AS linked_found_name
     FROM returned_items r
     LEFT JOIN categories c ON r.category_id = c.id
     LEFT JOIN users u ON r.recorded_by = u.id
     LEFT JOIN lost_items l ON r.lost_item_id = l.id
     LEFT JOIN found_items f ON r.found_item_id = f.id
     WHERE r.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Returned item record not found.');
    header('Location: ' . base_url('returned_items/index.php'));
    exit;
}

$pageTitle = 'Returned Item: ' . $item['item_name'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 1050px;">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                <div>
                    <a href="<?= base_url('returned_items/index.php') ?>" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-arrow-left"></i> Back to Returned Items
                    </a>
                    <h1 class="h3 fw-semibold mb-2"><?= sanitize($item['item_name']) ?></h1>
                    <span class="badge status-badge-claimed">STATUS: RETURNED TO OWNER</span>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="<?= base_url('returned_items/process.php') ?>" data-confirm="Delete this returned item record?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>

            <?= render_flash() ?>

            <div class="row g-4">
                <!-- Left Details Column -->
                <div class="col-12 col-lg-7">
                    <div class="card mb-4">
                        <div class="card-body p-4">
                            <h5 class="font-display fw-semibold mb-3">Return Handover Details</h5>

                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Received By</small>
                                    <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-user-check me-1 text-success"></i><?= sanitize($item['receiver_name']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Date Returned</small>
                                    <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-calendar-days me-1 text-primary"></i><?= format_date($item['date_returned']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Category</small>
                                    <div class="fw-semibold text-dark mt-1"><?= sanitize($item['category_name'] ?? 'General') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Receiver Contact / ID</small>
                                    <div class="fw-semibold text-dark mt-1"><?= sanitize($item['receiver_contact'] ?: '—') ?></div>
                                </div>
                            </div>

                            <h6 class="fw-semibold text-dark mb-2">Item Description</h6>
                            <div class="p-3 bg-light rounded-3 text-secondary mb-4 border">
                                <?= !empty($item['description']) ? nl2br(sanitize($item['description'])) : 'No description provided.' ?>
                            </div>

                            <?php if (!empty($item['notes'])): ?>
                                <h6 class="fw-semibold text-dark mb-2">Handover Notes</h6>
                                <div class="p-3 bg-light rounded-3 text-secondary small border mb-4">
                                    <i class="fa-solid fa-note-sticky me-1 text-primary"></i> <?= nl2br(sanitize($item['notes'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['lost_item_id']) || !empty($item['found_item_id'])): ?>
                                <h6 class="fw-semibold text-dark mb-2">Linked Registry Records</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!empty($item['lost_item_id'])): ?>
                                        <a href="<?= base_url('lost_items/view.php?id=' . (int)$item['lost_item_id']) ?>" class="btn btn-soft-primary btn-sm">
                                            <i class="fa-solid fa-circle-question me-1"></i>
                                            Lost Item #<?= (int)$item['lost_item_id'] ?><?= !empty($item['linked_lost_name']) ? ' (' . sanitize($item['linked_lost_name']) . ')' : '' ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($item['found_item_id'])): ?>
                                        <a href="<?= base_url('found_items/view.php?id=' . (int)$item['found_item_id']) ?>" class="btn btn-soft-primary btn-sm">
                                            <i class="fa-solid fa-box-open me-1"></i>
                                            Found Item #<?= (int)$item['found_item_id'] ?><?= !empty($item['linked_found_name']) ? ' (' . sanitize($item['linked_found_name']) . ')' : '' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body p-4">
                            <h6 class="font-display fw-semibold text-dark mb-3">Audit Details</h6>
                            <ul class="list-unstyled mb-0 small font-meta text-muted">
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Return Log ID</span>
                                    <span class="fw-bold text-dark">#<?= (int)$item['id'] ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span>Recorded By</span>
                                    <span class="fw-semibold text-dark"><?= sanitize($item['recorder_name'] ?? 'Staff') ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2">
                                    <span>Logged At</span>
                                    <span><?= format_datetime($item['created_at']) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Proof & Item Photos) -->
                <div class="col-12 col-lg-5">
                    <div class="card mb-4 overflow-hidden">
                        <div class="card-header py-3 px-4">
                            <h6 class="font-display fw-semibold mb-0 fs-6">
                                <i class="fa-solid fa-camera text-success me-1"></i> Proof of Return Photo
                            </h6>
                        </div>
                        <?php if (!empty($item['proof_image_path']) && item_image_exists($item['proof_image_path'])): ?>
                            <img src="<?= base_url($item['proof_image_path']) ?>" alt="Proof of return for <?= sanitize($item['item_name']) ?>" class="item-detail-image" data-fallback-kind="detail" data-fallback-icon="fa-solid fa-camera" data-fallback-text="Proof photo unavailable">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center">
                                <i class="fa-solid fa-camera fs-1 d-block mb-2 text-secondary"></i>
                                <span class="font-meta small">Proof photo unavailable</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card overflow-hidden">
                        <div class="card-header py-3 px-4">
                            <h6 class="font-display fw-semibold mb-0 fs-6">
                                <i class="fa-regular fa-image text-primary me-1"></i> Item Photo
                            </h6>
                        </div>
                        <?php if (!empty($item['item_image_path']) && item_image_exists($item['item_image_path'])): ?>
                            <img src="<?= base_url($item['item_image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-detail-image" data-fallback-kind="detail" data-fallback-icon="fa-regular fa-image" data-fallback-text="No item photo provided">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center">
                                <i class="fa-regular fa-image fs-1 d-block mb-2 text-secondary"></i>
                                <span class="font-meta small">No item photo provided</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
