<?php
/**
 * Root Entry Point
 * FoundU - Lost & Found Management System
 * Redirects to dashboard if authenticated, or login screen.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
} else {
    header('Location: ' . base_url('login.php'));
    exit;
}
