<?php
/**
 * Record Found Item Form
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Record Found Item';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 900px;">
            <div class="mb-4">
                <a href="<?= base_url('found_items/index.php') ?>" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Found Inventory
                </a>
                <h1 class="h3 fw-semibold mb-1">Record Found Item</h1>
                <p class="font-meta text-muted small mb-0">Catalog a recovered item turned into campus security or lost &amp; found office.</p>
            </div>

            <?= render_flash() ?>

            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="<?= base_url('found_items/process.php') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">

                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="itemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="itemName" name="item_name" placeholder="e.g., Wireless Earbuds, Silver Watch, Car Keys" required>
                                <div class="invalid-feedback">Please enter the item name.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="categoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoryId" name="category_id" required>
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="dateFound" class="form-label small">Date Found <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dateFound" name="date_found" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Please select the date found.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="locationFound" class="form-label small">Location Found <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="locationFound" name="location_found" placeholder="e.g., Cafeteria Booth #4, Gym Bleachers" required>
                                <div class="invalid-feedback">Please specify where it was picked up.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="storageLocation" class="form-label small">Storage Location / Locker ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="storageLocation" name="storage_location" placeholder="e.g., Locker #14, Admin Safe Bin B" required>
                                <div class="form-text small">Physical location where the item is stored in campus custody.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="itemImage" class="form-label small">Photo of Item (Optional)</label>
                                <input type="file" class="form-control" id="itemImage" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div class="form-text small">Accepted formats: JPG, JPEG, PNG, WEBP (Max 5MB).</div>
                                <div class="image-upload-preview-card mt-2 d-none" data-image-preview-box></div>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label small">Item Description &amp; Condition <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Condition, color, brand, distinct marks, stickers..." required></textarea>
                                <div class="invalid-feedback">Please describe the found item.</div>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label small">Internal Custody Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Turned in by student assistant, handed in with bag, etc."></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= base_url('found_items/index.php') ?>" class="btn btn-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">Save Found Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
