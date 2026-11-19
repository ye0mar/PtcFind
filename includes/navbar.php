<?php
/**
 * Navigation Bar Component
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/notifications.php';

$user = current_user();
$unreadNotificationsCount = $user ? get_unread_notification_count((int)$user['id']) : 0;
$currentScriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$onNotificationsPage = (strpos($currentScriptPath, '/notifications/') !== false);
$onProfilePage = (strpos($currentScriptPath, '/profile/') !== false);
?>
<nav class="navbar navbar-light sticky-top main-navbar">
    <div class="container-fluid px-4">
        <!-- Left Side: FoundU Brand / Logo Only -->
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none py-1" href="<?= base_url('dashboard.php') ?>">
            <span class="foundu-brand-mark" aria-hidden="true">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <span class="brand-wordmark">FoundU</span>
        </a>

        <!-- Right Side: Three Icon-Only Controls -->
        <div class="ms-auto d-flex align-items-center">
            <?php if ($user): ?>
                <div class="nav-icon-group">
                    <a href="<?= base_url('notifications/index.php') ?>"
                       class="nav-icon-btn nav-icon-bell<?= $onNotificationsPage ? ' active' : '' ?>"
                       id="navNotificationsBtn"
                       data-bs-toggle="tooltip"
                       data-bs-placement="bottom"
                       title="Notifications"
                       aria-label="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <span class="nav-unread-badge<?= $unreadNotificationsCount > 0 ? '' : ' d-none' ?>" id="navNotificationBadge">
                            <?= $unreadNotificationsCount > 99 ? '99+' : (int)$unreadNotificationsCount ?>
                        </span>
                    </a>

                    <a href="<?= base_url('profile/index.php') ?>"
                       class="nav-icon-btn nav-icon-profile<?= $onProfilePage ? ' active' : '' ?>"
                       id="navProfileBtn"
                       data-bs-toggle="tooltip"
                       data-bs-placement="bottom"
                       title="My Profile"
                       aria-label="My Profile">
                        <i class="fa-solid fa-user"></i>
                    </a>

                    <button type="button"
                            class="nav-icon-btn nav-icon-logout"
                            id="navLogoutBtn"
                            data-logout-trigger
                            data-bs-toggle="tooltip"
                            data-bs-placement="bottom"
                            title="Logout"
                            aria-label="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            <?php else: ?>
                <a href="<?= base_url('login.php') ?>" class="btn btn-primary btn-sm">Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
