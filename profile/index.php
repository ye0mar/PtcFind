<?php
/**
 * User Profile & Password Change
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
$user = current_user();

// Handle profile update & password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (!empty($fullName)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':fn' => $fullName, ':em' => $email ?: null, ':id' => $user['id']]);

                $_SESSION['full_name']  = $fullName;
                $_SESSION['user_email'] = $email;

                log_activity('UPDATE_PROFILE', 'users', $user['id'], "Updated personal profile information");
                set_flash('success', 'Profile information updated successfully.');
            } catch (Exception $e) {
                set_flash('error', 'Update error: ' . $e->getMessage());
            }
        } else {
            set_flash('error', 'Full name cannot be empty.');
        }

        header('Location: ' . base_url('profile/index.php'));
        exit;
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            set_flash('error', 'Please fill in all password fields.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('error', 'New password and confirmation do not match.');
        } elseif (strlen($newPass) < 6) {
            set_flash('error', 'New password must be at least 6 characters.');
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $user['id']]);
            $hash = $stmt->fetchColumn();

            if ($hash && password_verify($currentPass, $hash)) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $upStmt = $pdo->prepare("UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id");
                $upStmt->execute([':h' => $newHash, ':id' => $user['id']]);

                log_activity('CHANGE_PASSWORD', 'users', $user['id'], "Updated account security password");
                set_flash('success', 'Password updated successfully.');
            } else {
                set_flash('error', 'Current password is incorrect.');
            }
        }

        header('Location: ' . base_url('profile/index.php'));
        exit;
    }
}

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user['id']]);
$userData = $stmt->fetch();

$pageTitle = 'Operator Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 900px;">
            <div class="mb-4">
                <h1 class="h3 fw-semibold mb-1">My Operator Profile</h1>
                <p class="font-meta text-muted small mb-0">Manage your operator name, contact email, and account password.</p>
            </div>

            <?= render_flash() ?>

            <div class="row g-4">
                <!-- Profile Card -->
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-body p-4 p-md-5">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="user-avatar-circle" style="width: 58px; height: 58px; font-size: 1.5rem; border-radius: 15px;">
                                    <?= strtoupper(substr($userData['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h5 class="font-display fw-semibold text-dark mb-0"><?= sanitize($userData['full_name']) ?></h5>
                                    <small class="font-meta text-muted d-block">@<?= sanitize($userData['username']) ?></small>
                                    <span class="badge role-badge-<?= $userData['role'] === 'admin' ? 'admin' : 'staff' ?> mt-1">
                                        <?= strtoupper(sanitize($userData['role'])) ?>
                                    </span>
                                </div>
                            </div>

                            <form method="POST" action="<?= base_url('profile/index.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-3">
                                    <label for="profileFullName" class="form-label small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="profileFullName" name="full_name" value="<?= sanitize($userData['full_name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="profileEmail" class="form-label small">Email Address</label>
                                    <input type="email" class="form-control" id="profileEmail" name="email" value="<?= sanitize($userData['email']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small">Username (Read-Only)</label>
                                    <input type="text" class="form-control bg-light" value="<?= sanitize($userData['username']) ?>" readonly disabled>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary py-2">Save Profile Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Change Card -->
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-body p-4 p-md-5">
                            <h5 class="font-display fw-semibold text-dark mb-2">Change Password</h5>
                            <p class="font-meta text-muted small mb-4">Ensure your account uses a secure password with at least 6 characters.</p>

                            <form method="POST" action="<?= base_url('profile/index.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label for="currentPassword" class="form-label small">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="newPassword" class="form-label small">New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="newPassword" name="new_password" minlength="6" required>
                                </div>

                                <div class="mb-3">
                                    <label for="confirmPassword" class="form-label small">Confirm New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" minlength="6" required>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-outline-primary py-2">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
