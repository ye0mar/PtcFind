<?php
/**
 * Logout Handler
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash_messages.php';

logout_user();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
}
set_flash('info', 'You have been successfully logged out.');
header('Location: ' . base_url('login.php'));
exit;
