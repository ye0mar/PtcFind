<?php
/**
 * System Settings Configuration
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $systemName = trim($_POST['system_name'] ?? '');
    $orgName    = trim($_POST['organization_name'] ?? '');
    $perPage    = max(5, min(100, (int)($_POST['items_per_page'] ?? 15)));

    if (!empty($systemName) && !empty($orgName)) {
        try {
            $stmt = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES (:k, :v)");

            $stmt->execute([':k' => 'system_name', ':v' => $systemName]);
            $stmt->execute([':k' => 'organization_name', ':v' => $orgName]);
            $stmt->execute([':k' => 'items_per_page', ':v' => (string)$perPage]);

            log_activity('UPDATE_SETTINGS', 'system_settings', null, "Updated system settings (Name: {$systemName})");
            set_flash('success', 'System settings updated successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to update settings: ' . $e->getMessage());
        }
    } else {
        set_flash('error', 'Please fill in all required settings fields.');
    }

    header('Location: ' . base_url('admin/settings.php'));
    exit;
}

$systemName = get_setting('system_name', 'FoundU - Lost & Found Management System');
$orgName    = get_setting('organization_name', 'Polytechnic Campus Administration');
$perPage    = get_setting('items_per_page', '15');

$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 800px;">
            <div class="mb-4">
                <h1 class="h3 fw-semibold mb-1">System Configuration Settings</h1>
                <p class="font-meta text-muted small mb-0">Manage global portal branding, institution name, and pagination limits.</p>
            </div>

            <?= render_flash() ?>

            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="<?= base_url('admin/settings.php') ?>" class="needs-validation" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <label for="systemName" class="form-label small">Application / System Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="systemName" name="system_name" value="<?= sanitize($systemName) ?>" required>
                            <div class="form-text small">Displayed in headers, titles, and login portals.</div>
                        </div>

                        <div class="mb-4">
                            <label for="organizationName" class="form-label small">Organization / Institution Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="organizationName" name="organization_name" value="<?= sanitize($orgName) ?>" required>
                            <div class="form-text small">Campus administration department or school name.</div>
                        </div>

                        <div class="mb-4">
                            <label for="itemsPerPage" class="form-label small">Items Per Page (Pagination) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="itemsPerPage" name="items_per_page" value="<?= (int)$perPage ?>" min="5" max="100" required>
                            <div class="form-text small">Default number of lost and found rows shown on directory tables (5 - 100).</div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
