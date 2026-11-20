<?php
/**
 * User Login Page
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash_messages.php';

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $usernameVal = $username;

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Please contact an administrator.';
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Update hash if old format
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upStmt = $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
                        $upStmt->execute([':h' => $newHash, ':id' => $user['id']]);
                    }

                    login_user($user);
                    set_flash('success', "Welcome back, {$user['full_name']}!");
                    header('Location: ' . base_url('dashboard.php'));
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                    log_activity('FAILED_LOGIN', 'users', null, "Failed login attempt for username: {$username}");
                }
            } else {
                $error = 'Invalid username or password.';
                log_activity('FAILED_LOGIN', 'users', null, "Failed login attempt for nonexistent username: {$username}");
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Sign In';
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
                                <i class="fa-solid fa-magnifying-glass fs-3"></i>
                            </div>
                            <h3 class="auth-brand-title mb-1">FoundU</h3>
                            <p class="font-meta text-muted small mb-0"><?= sanitize(get_setting('system_name', 'Lost & Found Management System')) ?></p>
                        </div>

                        <!-- Flash / Error alerts -->
                        <?= render_flash() ?>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                                <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                                <div class="small"><?= sanitize($error) ?></div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Form -->
                        <form method="POST" action="<?= base_url('login.php') ?>" class="needs-validation" novalidate>
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label for="usernameInput" class="form-label small fw-medium">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="fa-solid fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light border-start-0" id="usernameInput" name="username" value="<?= sanitize($usernameVal) ?>" placeholder="Enter your username" required autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="passwordInput" class="form-label small fw-medium">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="fa-solid fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control bg-light border-start-0" id="passwordInput" name="password" placeholder="Enter your password" required>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                    <span>Login</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
