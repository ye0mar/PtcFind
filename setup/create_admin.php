<?php
/**
 * Initial Administrator Seeding Utility
 * FoundU - Lost & Found Management System
 * Run in browser via: http://localhost/foundu/setup/create_admin.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $fullName = trim($_POST['full_name'] ?? 'System Administrator');
    $username = trim($_POST['username'] ?? 'admin');
    $email    = trim($_POST['email'] ?? 'admin@foundu.edu');
    $password = $_POST['password'] ?? 'admin123';

    if (empty($username) || empty($password)) {
        $message = 'Username and password are required.';
        $status = 'danger';
    } else {
        try {
            $pdo = get_db_connection();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update password
                $up = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, password_hash = :ph, role = 'admin', status = 'active', updated_at = NOW() WHERE id = :id");
                $up->execute([':fn' => $fullName, ':em' => $email, ':ph' => $hash, ':id' => $existing['id']]);
                $message = "Admin user '{$username}' password was reset successfully!";
                $status = 'success';
            } else {
                $ins = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role, status, created_at) VALUES (:fn, :un, :em, :ph, 'admin', 'active', NOW())");
                $ins->execute([':fn' => $fullName, ':un' => $username, ':em' => $email, ':ph' => $hash]);
                $message = "New administrator '{$username}' created successfully!";
                $status = 'success';
            }
        } catch (Exception $e) {
            $message = 'Database error: ' . $e->getMessage();
            $status = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Administrator - FoundU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-5">
    <div class="container" style="max-width: 500px;">
        <div class="card p-4 p-sm-5">
            <h3 class="font-display fw-semibold text-primary mb-1">FoundU Setup</h3>
            <p class="font-meta text-muted small mb-4">Initialize or reset the administrator account in MySQL.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $status ?> small mb-4"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label small">Full Name</label>
                    <input type="text" class="form-control" name="full_name" value="System Administrator" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Username</label>
                    <input type="text" class="form-control" name="username" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Email Address</label>
                    <input type="email" class="form-control" name="email" value="admin@foundu.edu">
                </div>
                <div class="mb-4">
                    <label class="form-label small">Password</label>
                    <input type="text" class="form-control" name="password" value="admin123" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary py-2">Create / Reset Admin</button>
                    <a href="../login.php" class="btn btn-outline-secondary py-2 small">Go to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
