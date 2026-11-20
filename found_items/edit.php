<?php
/**
 * Edit Found Item Record
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . base_url('found_items/index.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM found_items WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Found item record not found.');
    header('Location: ' . base_url('found_items/index.php'));
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Edit Found Item: ' . $item['item_name'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 900px;">
            <div class="mb-4">
                <a href="<?= base_url('found_items/view.php?id=' . $item['id']) ?>" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Found Item Details
                </a>
                <h1 class="h3 fw-semibold mb-1">Edit Found Item #<?= $item['id'] ?></h1>
                <p class="font-meta text-muted small mb-0">Update item description, storage locker assignment, or custody status.</p>
            </div>

            <?= render_flash() ?>

            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="<?= base_url('found_items/process.php') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">

                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="itemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="itemName" name="item_name" value="<?= sanitize($item['item_name']) ?>" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="status" class="form-label small">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="available" <?= ($item['status'] === 'available') ? 'selected' : '' ?>>Available (In Storage)</option>
                                    <option value="matched" <?= ($item['status'] === 'matched') ? 'selected' : '' ?>>Matched (Linked)</option>
                                    <option value="returned" <?= ($item['status'] === 'returned') ? 'selected' : '' ?>>Returned (Claimed)</option>
                                    <option value="closed" <?= ($item['status'] === 'closed') ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="categoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoryId" name="category_id" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($item['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= sanitize($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="dateFound" class="form-label small">Date Found <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dateFound" name="date_found" value="<?= sanitize($item['date_found']) ?>" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="locationFound" class="form-label small">Location Found <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="locationFound" name="location_found" value="<?= sanitize($item['location_found']) ?>" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="storageLocation" class="form-label small">Storage Location / Locker <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="storageLocation" name="storage_location" value="<?= sanitize($item['storage_location']) ?>" required>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label small">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" required><?= sanitize($item['description']) ?></textarea>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label small">Internal Custody Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?= sanitize($item['notes']) ?></textarea>
                            </div>

                            <?php $hasExistingImage = !empty($item['image_path']) && item_image_exists($item['image_path']); ?>
                            <div class="col-12">
                                <label for="itemImage" class="form-label small">Replace Photo (Optional)</label>
                                <input type="file" class="form-control" id="itemImage" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div class="form-text small">Accepted formats: JPG, JPEG, PNG, WEBP (Max 5MB).</div>
                                <div class="image-upload-preview-card mt-2 <?= $hasExistingImage ? '' : 'd-none' ?>"
                                     data-image-preview-box
                                     data-existing-src="<?= $hasExistingImage ? base_url($item['image_path']) : '' ?>"
                                     data-existing-name="<?= $hasExistingImage ? sanitize(basename($item['image_path'])) : '' ?>">
                                    <?php if ($hasExistingImage): ?>
                                        <img src="<?= base_url($item['image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-edit-preview" data-fallback-kind="edit">
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-dark small text-truncate"><?= sanitize(basename($item['image_path'])) ?></div>
                                            <div class="font-meta text-muted small">Current uploaded photo &middot; Leave empty to keep</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= base_url('found_items/view.php?id=' . $item['id']) ?>" class="btn btn-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
