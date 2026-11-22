<?php
/**
 * Item Matches & Claims Management
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
ensure_returned_items_table($pdo);

// Fetch matches with lost, found, and return details
$matchesQuery = "
    SELECT m.*,
           l.item_name AS lost_name, l.date_lost, l.location_lost, l.contact_info, l.status AS lost_status, l.image_path AS lost_image,
           f.item_name AS found_name, f.date_found, f.storage_location, f.status AS found_status, f.image_path AS found_image,
           u.full_name AS confirmed_by_name,
           (SELECT r.id
            FROM returned_items r
            WHERE r.match_id = m.id OR r.lost_item_id = m.lost_item_id OR r.found_item_id = m.found_item_id
            ORDER BY r.id DESC
            LIMIT 1) AS returned_item_id
    FROM matches m
    JOIN lost_items l ON m.lost_item_id = l.id
    JOIN found_items f ON m.found_item_id = f.id
    LEFT JOIN users u ON m.confirmed_by = u.id
    ORDER BY m.created_at DESC, m.id DESC
";
$matches = $pdo->query($matchesQuery)->fetchAll();

// Fetch open lost items for the new match modal
$openLostItems = $pdo->query("SELECT id, item_name, date_lost FROM lost_items WHERE status = 'open' ORDER BY item_name ASC")->fetchAll();

// Fetch available found items for the new match modal
$availFoundItems = $pdo->query("SELECT id, item_name, storage_location FROM found_items WHERE status = 'available' ORDER BY item_name ASC")->fetchAll();

$pageTitle = 'Matches & Claims';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid">
            <!-- Header bar -->
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-semibold mb-1">Item Matches &amp; Claims</h1>
                    <p class="font-meta text-muted small mb-0">Link reported lost items with recovered items in storage to coordinate owner verification and return.</p>
                </div>
                <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#newMatchModal">
                    <i class="fa-solid fa-link"></i>
                    <span>Create New Match</span>
                </button>
            </div>

            <?= render_flash() ?>

            <!-- Matches Table -->
            <div class="card overflow-hidden">
                <div class="card-body p-0">
                    <?php if (empty($matches)): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-link fs-1 text-muted"></i>
                            <h5 class="font-display fw-semibold text-dark mt-3">No matches created yet</h5>
                            <p class="font-meta text-muted small">Link an open lost report with an item currently kept in storage.</p>
                            <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#newMatchModal">
                                <i class="fa-solid fa-plus"></i> Create First Match
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Match ID</th>
                                        <th>Lost Item Report</th>
                                        <th>Found Custody Item</th>
                                        <th>Confirmed By</th>
                                        <th>Verification Notes</th>
                                        <th>Date Linked</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matches as $m): ?>
                                        <tr>
                                            <td class="ps-4 font-meta fw-bold text-primary">#<?= $m['id'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($m['lost_image']) && item_image_exists($m['lost_image'])): ?>
                                                        <img src="<?= base_url($m['lost_image']) ?>" alt="<?= sanitize($m['lost_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-regular fa-image">
                                                    <?php else: ?>
                                                        <div class="item-thumb-placeholder">
                                                            <i class="fa-regular fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="<?= base_url('lost_items/view.php?id=' . $m['lost_item_id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                                            <?= sanitize($m['lost_name']) ?>
                                                        </a>
                                                        <small class="font-meta text-muted">Contact: <?= sanitize($m['contact_info']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($m['found_image']) && item_image_exists($m['found_image'])): ?>
                                                        <img src="<?= base_url($m['found_image']) ?>" alt="<?= sanitize($m['found_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-solid fa-box-open">
                                                    <?php else: ?>
                                                        <div class="item-thumb-placeholder">
                                                            <i class="fa-solid fa-box-open"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="<?= base_url('found_items/view.php?id=' . $m['found_item_id']) ?>" class="fw-semibold text-dark text-decoration-none d-block mb-1">
                                                            <?= sanitize($m['found_name']) ?>
                                                        </a>
                                                        <span class="storage-badge">
                                                            <i class="fa-solid fa-box-archive"></i>
                                                            <span><?= sanitize($m['storage_location']) ?></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="small fw-medium text-secondary">
                                                <?= sanitize($m['confirmed_by_name'] ?? 'Staff') ?>
                                            </td>
                                            <td class="small font-meta text-muted" style="max-width: 200px;">
                                                <?= sanitize($m['notes'] ?: '—') ?>
                                            </td>
                                            <td class="small font-meta text-muted text-nowrap"><?= format_datetime($m['created_at']) ?></td>
                                            <td class="text-end pe-4">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <?php if (!empty($m['returned_item_id'])): ?>
                                                        <a href="<?= base_url('returned_items/view.php?id=' . (int)$m['returned_item_id']) ?>" class="badge status-badge-claimed text-decoration-none" title="View Return Record &amp; Proof Photo">
                                                            RETURNED <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                                        </a>
                                                    <?php elseif ($m['lost_status'] !== 'returned' && $m['found_status'] !== 'returned'): ?>
                                                        <a href="<?= base_url('returned_items/add.php?match_id=' . (int)$m['id']) ?>" class="btn btn-success btn-sm" title="Record Item Return with Receiver &amp; Proof Photo">
                                                            <i class="fa-solid fa-clipboard-check"></i>
                                                            <span>Return</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge status-badge-claimed">RETURNED</span>
                                                    <?php endif; ?>

                                                    <form method="POST" action="<?= base_url('matches/process.php') ?>" class="d-inline" data-confirm="Unlink this match? Items will return to open/available status.">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-action-icon" title="Unlink match">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
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
    </main>
</div>

<!-- New Match Modal -->
<div class="modal fade" id="newMatchModal" tabindex="-1" aria-labelledby="newMatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-display fw-semibold text-dark" id="newMatchModalLabel">Create Item Match</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= base_url('matches/process.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="lostItemId" class="form-label small">Select Lost Item Report <span class="text-danger">*</span></label>
                        <select class="form-select" id="lostItemId" name="lost_item_id" required>
                            <option value="">Choose open lost item...</option>
                            <?php foreach ($openLostItems as $li): ?>
                                <option value="<?= $li['id'] ?>">
                                    #<?= $li['id'] ?> - <?= sanitize($li['item_name']) ?> (Lost: <?= format_date($li['date_lost']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="foundItemId" class="form-label small">Select Found Item in Custody <span class="text-danger">*</span></label>
                        <select class="form-select" id="foundItemId" name="found_item_id" required>
                            <option value="">Choose available found item...</option>
                            <?php foreach ($availFoundItems as $fi): ?>
                                <option value="<?= $fi['id'] ?>">
                                    #<?= $fi['id'] ?> - <?= sanitize($fi['item_name']) ?> (Locker: <?= sanitize($fi['storage_location']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="matchNotes" class="form-label small">Verification &amp; Handover Notes</label>
                        <textarea class="form-control" id="matchNotes" name="notes" rows="3" placeholder="Student ID verified, serial number matched, pickup date scheduled..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Confirm Match</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
