<?php
/**
 * Authentication & Session Management
 * FoundU - Lost & Found Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/flash_messages.php';
require_once __DIR__ . '/csrf.php';

/**
 * Check if the visitor is an authenticated operator.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the currently logged-in user has the 'admin' role.
 */
function is_admin(): bool {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current authenticated user session data array.
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'staff',
        'email'     => $_SESSION['user_email'] ?? '',
    ];
}

/**
 * Require authentication. Redirects to login page if unauthenticated.
 */
function require_login(): void {
    if (!is_logged_in()) {
        require_once __DIR__ . '/flash_messages.php';
        set_flash('error', 'Please log in to access this page.');
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Require admin role. Redirects or aborts with 403 if user is not admin.
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        require_once __DIR__ . '/flash_messages.php';
        set_flash('error', 'Access denied. Administrator privileges required.');
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}

/**
 * Log in a user by setting session variables and updating last_login timestamp.
 */
function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_email'] = $user['email'] ?? '';

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);
    } catch (Exception $e) {
        // Non-critical, continue login
    }

    log_activity('LOGIN', 'users', $user['id'], "User {$user['username']} logged in successfully");
}

/**
 * Log out user, destroy session and clear session cookie.
 */
function logout_user(): void {
    if (is_logged_in()) {
        log_activity('LOGOUT', 'users', $_SESSION['user_id'], "User {$_SESSION['username']} logged out");
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
