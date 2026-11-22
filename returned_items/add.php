<?php
/**
 * Record Returned Item Form
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
ensure_returned_items_table($pdo);

$matchId = (int)($_GET['match_id'] ?? 0);
$prefillLostId = (int)($_GET['lost_item_id'] ?? 0);
$prefillFoundId = (int)($_GET['found_item_id'] ?? 0);

$prefillName = '';
$prefillCategoryId = '';
$prefillDescription = '';
$prefillContact = '';

if ($matchId > 0) {
    $matchStmt = $pdo->prepare(
        "SELECT m.*, l.item_name AS lost_name, l.category_id AS lost_cat, l.description AS lost_desc, l.contact_info,
                f.item_name AS found_name, f.category_id AS found_cat, f.description AS found_desc
         FROM matches m
         JOIN lost_items l ON m.lost_item_id = l.id
         JOIN found_items f ON m.found_item_id = f.id
         WHERE m.id = :id LIMIT 1"
    );
    $matchStmt->execute([':id' => $matchId]);
    $matchRow = $matchStmt->fetch();
    if ($matchRow) {
        $existingStmt = $pdo->prepare(
            "SELECT id FROM returned_items WHERE match_id = :mid OR lost_item_id = :lid OR found_item_id = :fid LIMIT 1"
        );
        $existingStmt->execute([
            ':mid' => $matchId,
            ':lid' => (int)$matchRow['lost_item_id'],
            ':fid' => (int)$matchRow['found_item_id'],
        ]);
        $existingId = (int)$existingStmt->fetchColumn();
        if ($existingId > 0) {
            set_flash('info', 'This item has already been recorded as returned.');
            header('Location: ' . base_url('returned_items/view.php?id=' . $existingId));
            exit;
        }
        $prefillLostId = (int)$matchRow['lost_item_id'];
        $prefillFoundId = (int)$matchRow['found_item_id'];
        $prefillName = (string)($matchRow['found_name'] ?: $matchRow['lost_name']);
        $prefillCategoryId = (string)($matchRow['found_cat'] ?: $matchRow['lost_cat'] ?: '');
        $prefillDescription = (string)($matchRow['found_desc'] ?: $matchRow['lost_desc']);
        $prefillContact = (string)($matchRow['contact_info'] ?? '');
    }
} elseif ($prefillFoundId > 0) {
    $dupStmt = $pdo->prepare("SELECT id FROM returned_items WHERE found_item_id = :fid LIMIT 1");
    $dupStmt->execute([':fid' => $prefillFoundId]);
    $dupId = (int)$dupStmt->fetchColumn();
    if ($dupId > 0) {
        set_flash('info', 'This found item has already been recorded as returned.');
        header('Location: ' . base_url('returned_items/view.php?id=' . $dupId));
        exit;
    }
    $fStmt = $pdo->prepare("SELECT * FROM found_items WHERE id = :id LIMIT 1");
    $fStmt->execute([':id' => $prefillFoundId]);
    if ($fRow = $fStmt->fetch()) {
        $prefillName = (string)$fRow['item_name'];
        $prefillCategoryId = (string)($fRow['category_id'] ?? '');
        $prefillDescription = (string)($fRow['description'] ?? '');
    }
} elseif ($prefillLostId > 0) {
    $dupStmt = $pdo->prepare("SELECT id FROM returned_items WHERE lost_item_id = :lid LIMIT 1");
    $dupStmt->execute([':lid' => $prefillLostId]);
    $dupId = (int)$dupStmt->fetchColumn();
    if ($dupId > 0) {
        set_flash('info', 'This lost item has already been recorded as returned.');
        header('Location: ' . base_url('returned_items/view.php?id=' . $dupId));
        exit;
    }
    $lStmt = $pdo->prepare("SELECT * FROM lost_items WHERE id = :id LIMIT 1");
    $lStmt->execute([':id' => $prefillLostId]);
    if ($lRow = $lStmt->fetch()) {
        $prefillName = (string)$lRow['item_name'];
        $prefillCategoryId = (string)($lRow['category_id'] ?? '');
        $prefillDescription = (string)($lRow['description'] ?? '');
        $prefillContact = (string)($lRow['contact_info'] ?? '');
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$availableLostItems = $pdo->query(
    "SELECT l.id, l.item_name, l.category_id, l.description, l.date_lost, l.contact_info, l.status
     FROM lost_items l
     LEFT JOIN returned_items r ON r.lost_item_id = l.id
     WHERE l.status IN ('open', 'matched') AND r.id IS NULL
     ORDER BY l.created_at DESC, l.id DESC"
)->fetchAll();

$availableFoundItems = $pdo->query(
    "SELECT f.id, f.item_name, f.category_id, f.description, f.storage_location, f.status
     FROM found_items f
     LEFT JOIN returned_items r ON r.found_item_id = f.id
     WHERE f.status IN ('available', 'matched') AND r.id IS NULL
     ORDER BY f.created_at DESC, f.id DESC"
)->fetchAll();

$pageTitle = 'Record Returned Item';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 920px;">
            <div class="mb-4">
                <a href="<?= base_url('returned_items/index.php') ?>" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Returned Items
                </a>
                <h1 class="h3 fw-semibold mb-1">Record Returned Item</h1>
                <p class="font-meta text-muted small mb-0">Log a verified handover with the receiver's name, return date, and proof photo.</p>
            </div>

            <?= render_flash() ?>

            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="<?= base_url('returned_items/process.php') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="match_id" value="<?= $matchId > 0 ? $matchId : '' ?>">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="foundItemSelect" class="form-label small">Link Found Item Record (Optional)</label>
                                <select class="form-select" id="foundItemSelect" name="found_item_id" data-return-link-select>
                                    <option value="">None / Standalone Return</option>
                                    <?php foreach ($availableFoundItems as $fi): ?>
                                        <option value="<?= (int)$fi['id'] ?>"
                                                data-item-name="<?= sanitize($fi['item_name']) ?>"
                                                data-category-id="<?= (int)($fi['category_id'] ?? 0) ?>"
                                                data-description="<?= sanitize($fi['description']) ?>"
                                                <?= ($prefillFoundId === (int)$fi['id']) ? 'selected' : '' ?>>
                                            #<?= (int)$fi['id'] ?> - <?= sanitize($fi['item_name']) ?> (<?= sanitize($fi['storage_location']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="lostItemSelect" class="form-label small">Link Lost Item Report (Optional)</label>
                                <select class="form-select" id="lostItemSelect" name="lost_item_id" data-return-link-select>
                                    <option value="">None / Walk-in Claim</option>
                                    <?php foreach ($availableLostItems as $li): ?>
                                        <option value="<?= (int)$li['id'] ?>"
                                                data-item-name="<?= sanitize($li['item_name']) ?>"
                                                data-category-id="<?= (int)($li['category_id'] ?? 0) ?>"
                                                data-description="<?= sanitize($li['description']) ?>"
                                                data-contact="<?= sanitize($li['contact_info']) ?>"
                                                <?= ($prefillLostId === (int)$li['id']) ? 'selected' : '' ?>>
                                            #<?= (int)$li['id'] ?> - <?= sanitize($li['item_name']) ?> (<?= format_date($li['date_lost']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-8">
                                <label for="returnItemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="returnItemName" name="item_name" value="<?= sanitize($prefillName) ?>" placeholder="e.g., iPhone 15 Pro, Campus ID Card, Leather Wallet" required>
                                <div class="invalid-feedback">Please provide the returned item name.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="returnCategoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="returnCategoryId" name="category_id" required>
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>" <?= ($prefillCategoryId !== '' && (int)$prefillCategoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                                            <?= sanitize($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>

                            <div class="col-12">
                                <label for="returnDescription" class="form-label small">Item Description</label>
                                <textarea class="form-control" id="returnDescription" name="description" rows="3" placeholder="Color, brand, serial marks, or condition at handover..."><?= sanitize($prefillDescription) ?></textarea>
                            </div>

                            <div class="col-12 col-md-5">
                                <label for="receiverName" class="form-label small">Receiver Name (Claimant) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="receiverName" name="receiver_name" placeholder="Full name of person receiving item" required>
                                <div class="invalid-feedback">Please enter the receiver's full name.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="receiverContact" class="form-label small">Receiver Contact / ID</label>
                                <input type="text" class="form-control" id="receiverContact" name="receiver_contact" value="<?= sanitize($prefillContact) ?>" placeholder="Email, phone, or student ID">
                            </div>

                            <div class="col-12 col-md-3">
                                <label for="dateReturned" class="form-label small">Date Returned <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dateReturned" name="date_returned" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Please select the return date.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="returnedItemImage" class="form-label small">Item Photo (Optional)</label>
                                <input type="file" class="form-control" id="returnedItemImage" name="item_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div class="form-text small">Leave empty to keep the linked Lost/Found photo if available.</div>
                                <div class="image-upload-preview-card mt-2 d-none" data-image-preview-box></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="proofImage" class="form-label small">Proof of Return Photo <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="proofImage" name="proof_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                <div class="form-text small">Handover photo or signed release evidence (JPG, PNG, WEBP, Max 5MB).</div>
                                <div class="invalid-feedback">Please upload a proof photo showing the return handover.</div>
                                <div class="image-upload-preview-card mt-2 d-none" data-image-preview-box></div>
                            </div>

                            <div class="col-12">
                                <label for="returnNotes" class="form-label small">Handover Notes (Optional)</label>
                                <textarea class="form-control" id="returnNotes" name="notes" rows="2" placeholder="Verification ID checked, remarks during release..."></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= base_url('returned_items/index.php') ?>" class="btn btn-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">Save Return Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
