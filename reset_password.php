<?php
/**
 * Staff Password Recovery Page
 * FoundU - Lost & Found Management System
 */

define('FOUNDU_INTERNAL_ACCESS', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash_messages.php';
require_once __DIR__ . '/recovery_config.php';

$error = '';
$success = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $username        = trim($_POST['username'] ?? '');
    $recoveryCode    = trim($_POST['recovery_code'] ?? '');
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $usernameVal     = $username;

    if ($username === '' || $recoveryCode === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Please complete all fields to reset your password.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation password do not match.';
    } elseif (!defined('STAFF_RECOVERY_CODE') || STAFF_RECOVERY_CODE === '' || !hash_equals((string)STAFF_RECOVERY_CODE, $recoveryCode)) {
        $error = 'Invalid recovery code. Please contact your system administrator.';
        log_activity('FAILED_PASSWORD_RESET', 'users', null, "Failed staff password reset (invalid recovery code) for username: {$username}");
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id, username, role, status FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $error = 'No staff account matched that username and recovery code.';
                log_activity('FAILED_PASSWORD_RESET', 'users', null, "Failed staff password reset (nonexistent username: {$username})");
            } elseif ($targetUser['role'] !== 'staff') {
                $error = 'Self-service recovery with a staff recovery code is restricted to active staff accounts.';
                log_activity('FAILED_PASSWORD_RESET', 'users', (int)$targetUser['id'], "Blocked non-staff password reset attempt for @{$username}");
            } elseif ($targetUser['status'] !== 'active') {
                $error = 'Your account is currently inactive. Please contact an administrator.';
                log_activity('FAILED_PASSWORD_RESET', 'users', (int)$targetUser['id'], "Blocked inactive staff password reset for @{$username}");
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upStmt = $pdo->prepare("UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id");
                $upStmt->execute([
                    ':h'  => $newHash,
                    ':id' => (int)$targetUser['id'],
                ]);

                log_activity('STAFF_PASSWORD_RESET', 'users', (int)$targetUser['id'], "Staff password reset completed for @{$targetUser['username']}");
                $success = 'Your password has been reset successfully. You can now sign in with your new password.';
                $usernameVal = '';
            }
        } catch (Exception $e) {
            $error = 'Unable to reset password right now: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Reset Staff Password';
$extraCss = ['assets/css/auth.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page d-flex align-items-center justify-content-center min-vh-100 py-5 bg-gradient-navy">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
                <div class="card auth-card border-0 overflow-hidden">
                    <div class="card-body p-4 p-sm-5">
                        <!-- Branding -->
                        <div class="text-center mb-4">
                            <div class="auth-brand-icon mx-auto mb-3 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-key fs-3"></i>
                            </div>
                            <h3 class="auth-brand-title mb-1">Staff Password Reset</h3>
                            <p class="font-meta text-muted small mb-0">Enter your staff username and administrator recovery code.</p>
                        </div>

                        <?= render_flash() ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                                <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                                <div class="small"><?= sanitize($error) ?></div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success d-flex align-items-start gap-2 mb-4" role="alert">
                                <i class="fa-solid fa-circle-check mt-1 fs-5"></i>
                                <div class="small">
                                    <div class="fw-semibold mb-1">Password Updated</div>
                                    <div><?= sanitize($success) ?></div>
                                </div>
                            </div>
                            <div class="d-grid">
                                <a href="<?= base_url('login.php') ?>" class="btn btn-primary py-2 fw-semibold">
                                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="<?= base_url('reset_password.php') ?>" class="needs-validation" novalidate autocomplete="off">
                                <?= csrf_field() ?>

                                <div class="mb-3">
                                    <label for="resetUsername" class="form-label small fw-medium">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="fa-solid fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control bg-light border-start-0" id="resetUsername" name="username" value="<?= sanitize($usernameVal) ?>" placeholder="Enter your staff username" required autofocus>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="recoveryCode" class="form-label small fw-medium">Recovery Code</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </span>
                                        <input type="password" class="form-control bg-light border-start-0" id="recoveryCode" name="recovery_code" placeholder="Enter administrator recovery code" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="newPassword" class="form-label small fw-medium">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control bg-light border-start-0" id="newPassword" name="new_password" placeholder="At least 6 characters" minlength="6" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="confirmPassword" class="form-label small fw-medium">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control bg-light border-start-0" id="confirmPassword" name="confirm_password" placeholder="Re-enter new password" minlength="6" required>
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                        <span>Reset Password</span>
                                    </button>
                                </div>

                                <div class="text-center">
                                    <a href="<?= base_url('login.php') ?>" class="small font-meta text-decoration-none text-muted">
                                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
