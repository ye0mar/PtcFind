<?php
/**
 * Sidebar Navigation Component
 * FoundU - Lost & Found Management System
 */

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$user = current_user();
?>
<aside class="sidebar-wrapper py-4 px-3 d-flex flex-column">
    <div class="sidebar-heading">
        Core Navigation
    </div>
    <ul class="nav nav-pills flex-column mb-3">
        <li class="nav-item">
            <a href="<?= base_url('dashboard.php') ?>" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high fa-fw"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('lost_items/index.php') ?>" class="nav-link <?= ($currentDir === 'lost_items') ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-question fa-fw"></i>
                <span>Lost Items</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('found_items/index.php') ?>" class="nav-link <?= ($currentDir === 'found_items') ? 'active' : '' ?>">
                <i class="fa-solid fa-box-open fa-fw"></i>
                <span>Found Items</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('matches/index.php') ?>" class="nav-link <?= ($currentDir === 'matches') ? 'active' : '' ?>">
                <i class="fa-solid fa-link fa-fw"></i>
                <span>Matches &amp; Claims</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('returned_items/index.php') ?>" class="nav-link <?= ($currentDir === 'returned_items') ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-check fa-fw"></i>
                <span>Returned Items</span>
            </a>
        </li>
    </ul>

    <?php if (is_admin()): ?>
        <div class="sidebar-divider"></div>
        <div class="sidebar-heading">
            Administration
        </div>
        <ul class="nav nav-pills flex-column mb-0">
            <li class="nav-item">
                <a href="<?= base_url('admin/manage_users.php') ?>" class="nav-link <?= ($currentPage === 'manage_users.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users fa-fw"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/activity_log.php') ?>" class="nav-link <?= ($currentPage === 'activity_log.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left fa-fw"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/backup_recovery.php') ?>" class="nav-link <?= ($currentPage === 'backup_recovery.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-database fa-fw"></i>
                    <span>Backup &amp; Recovery</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/system_monitor.php') ?>" class="nav-link <?= ($currentPage === 'system_monitor.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-microchip fa-fw"></i>
                    <span>System Monitor</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/settings.php') ?>" class="nav-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-sliders fa-fw"></i>
                    <span>System Settings</span>
                </a>
            </li>
        </ul>
    <?php endif; ?>
</aside>
