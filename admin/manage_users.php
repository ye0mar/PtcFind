<?php
/**
 * Admin User Management
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
$currentUser = current_user();

// Handle User Actions (Create, Toggle Status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin', 'staff']) ? $_POST['role'] : 'staff';

        if (empty($fullName) || empty($username) || empty($password)) {
            set_flash('error', 'Please fill in all required user fields.');
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, username, email, password_hash, role, status, created_at)
                     VALUES (:fn, :un, :em, :ph, :ro, 'active', NOW())"
                );
                $stmt->execute([
                    ':fn' => $fullName,
                    ':un' => $username,
                    ':em' => $email ?: null,
                    ':ph' => $hash,
                    ':ro' => $role,
                ]);

                log_activity('CREATE_USER', 'users', (int)$pdo->lastInsertId(), "Created operator account @{$username} with role {$role}");
                set_flash('success', "Operator account @{$username} created successfully.");
            } catch (Exception $e) {
                set_flash('error', 'Failed to create user (username or email may already exist): ' . $e->getMessage());
            }
        }
        header('Location: ' . base_url('admin/manage_users.php'));
        exit;
    }

    if ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';

        if (!$userId) {
            set_flash('error', 'Invalid user ID.');
        } elseif ($userId === (int)$currentUser['id']) {
            set_flash('error', 'You cannot change the status of your own account.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status = :st, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':st' => $status, ':id' => $userId]);
            log_activity('UPDATE_USER_STATUS', 'users', $userId, "Changed status of user #{$userId} to {$status}");
            set_flash('success', "User account status updated to {$status}.");
        }
        header('Location: ' . base_url('admin/manage_users.php'));
        exit;
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if (!$userId) {
            set_flash('error', 'Invalid user ID.');
        } elseif ($userId === (int)$currentUser['id']) {
            set_flash('error', 'You cannot delete your own account.');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                log_activity('DELETE_USER', 'users', $userId, "Deleted user #{$userId}");
                set_flash('success', "User account deleted successfully.");
            } catch (Exception $e) {
                set_flash('error', 'Cannot delete user with associated item records: ' . $e->getMessage());
            }
        }
        header('Location: ' . base_url('admin/manage_users.php'));
        exit;
    }
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Manage Users';
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
                    <h1 class="h3 fw-semibold mb-1">User &amp; Operator Management</h1>
                    <p class="font-meta text-muted small mb-0">Manage staff and administrative accounts authorized to access the FoundU system.</p>
                </div>
                <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add New Operator</span>
                </button>
            </div>

            <?= render_flash() ?>

            <!-- Users Table Card -->
            <div class="card overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="user-avatar-circle" style="width: 38px; height: 38px; font-size: 0.95rem;">
                                                    <?= sanitize(function_exists('mb_substr') ? (mb_strtoupper(mb_substr($u['full_name'] ?: 'U', 0, 1, 'UTF-8'), 'UTF-8') ?: 'U') : (strtoupper(substr($u['full_name'] ?: 'U', 0, 1)) ?: 'U')) ?>
                                                </div>
                                                <div class="fw-semibold text-dark"><?= sanitize($u['full_name']) ?></div>
                                            </div>
                                        </td>
                                        <td class="font-meta text-muted">@<?= sanitize($u['username']) ?></td>
                                        <td class="small font-meta text-muted"><?= sanitize($u['email'] ?: '—') ?></td>
                                        <td>
                                            <span class="badge role-badge-<?= $u['role'] === 'admin' ? 'admin' : 'staff' ?>">
                                                <?= strtoupper(sanitize($u['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-badge-<?= $u['status'] === 'active' ? 'active' : 'inactive' ?>">
                                                <?= strtoupper(sanitize($u['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="small font-meta text-muted text-nowrap"><?= format_datetime($u['last_login']) ?></td>
                                        <td class="small font-meta text-muted text-nowrap"><?= format_date($u['created_at']) ?></td>
                                        <td class="text-end pe-4">
                                            <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <!-- Toggle Status -->
                                                    <form method="POST" action="<?= base_url('admin/manage_users.php') ?>" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                                                        <button type="submit" class="btn <?= $u['status'] === 'active' ? 'btn-warning' : 'btn-success' ?> btn-action-icon" title="<?= $u['status'] === 'active' ? 'Deactivate Account' : 'Activate Account' ?>">
                                                            <i class="fa-solid fa-power-off"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Delete User -->
                                                    <form method="POST" action="<?= base_url('admin/manage_users.php') ?>" class="d-inline" data-confirm="Delete user @<?= sanitize($u['username']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-action-icon" title="Delete User">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="font-meta small text-muted">Current User</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-display fw-semibold text-dark" id="addUserModalLabel">Add New Operator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= base_url('admin/manage_users.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="newFullName" class="form-label small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newFullName" name="full_name" placeholder="John Doe" required>
                    </div>

                    <div class="mb-3">
                        <label for="newUsername" class="form-label small">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newUsername" name="username" placeholder="johndoe" required>
                    </div>

                    <div class="mb-3">
                        <label for="newEmail" class="form-label small">Email Address</label>
                        <input type="email" class="form-control" id="newEmail" name="email" placeholder="john@foundu.edu">
                    </div>

                    <div class="mb-3">
                        <label for="newPassword" class="form-label small">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="newPassword" name="password" placeholder="At least 6 characters" minlength="6" required>
                    </div>

                    <div class="mb-3">
                        <label for="newRole" class="form-label small">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="newRole" name="role" required>
                            <option value="staff" selected>Staff Operator (Log items, manage claims)</option>
                            <option value="admin">Administrator (Full system &amp; user access)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
