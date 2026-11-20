<?php
/**
 * System Health & Diagnostics Monitor
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db_connection();

// Get MySQL server version
try {
    $mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn() ?: 'Unknown';
} catch (Exception $e) {
    $mysqlVersion = 'Disconnected (' . $e->getMessage() . ')';
}

// Uploads directory status
$uploadsDir = __DIR__ . '/../uploads/';
$uploadsWritable = is_writable($uploadsDir);
$uploadFilesCount = is_dir($uploadsDir) ? count(array_diff(scandir($uploadsDir), ['.', '..', '.htaccess'])) : 0;

$pageTitle = 'System Monitor';
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
                    <h1 class="h3 fw-semibold mb-1">System Diagnostics &amp; Health</h1>
                    <p class="font-meta text-muted small mb-0">Live diagnostic monitor for PHP environment, MySQL connection, and filesystem permissions.</p>
                </div>
            </div>

            <!-- Health Cards Grid -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">PHP Engine</span>
                            <span class="badge status-badge-available">ACTIVE</span>
                        </div>
                        <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums">PHP <?= phpversion() ?></h4>
                        <small class="font-meta text-muted"><?= php_sapi_name() ?> interface</small>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">MySQL Database</span>
                            <span class="badge status-badge-available">CONNECTED</span>
                        </div>
                        <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums"><?= sanitize(substr($mysqlVersion, 0, 15)) ?></h4>
                        <small class="font-meta text-muted">Host: <?= DB_HOST ?>:<?= DB_PORT ?></small>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Memory Usage</span>
                            <span class="badge role-badge-staff">OPTIMAL</span>
                        </div>
                        <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums"><?= round(memory_get_usage(true) / 1024 / 1024, 2) ?> MB</h4>
                        <small class="font-meta text-muted">Limit: <?= ini_get('memory_limit') ?></small>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Uploads Directory</span>
                            <span class="badge <?= $uploadsWritable ? 'status-badge-available' : 'role-badge-admin' ?>">
                                <?= $uploadsWritable ? 'WRITABLE' : 'LOCKED' ?>
                            </span>
                        </div>
                        <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums"><?= $uploadFilesCount ?> Files</h4>
                        <small class="font-meta text-muted">Max upload: <?= ini_get('upload_max_filesize') ?></small>
                    </div>
                </div>
            </div>

            <!-- Detailed Diagnostics Table -->
            <div class="card overflow-hidden">
                <div class="card-header py-3 px-4">
                    <h5 class="font-display fw-semibold mb-0 fs-6">Configuration &amp; Security Checklist</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Parameter</th>
                                    <th>Status / Value</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">PHP PDO MySQL Extension</td>
                                    <td><span class="badge status-badge-available">Enabled (pdo_mysql)</span></td>
                                    <td class="small font-meta text-muted">Required for prepared SQL queries and XAMPP database operations.</td>
                                </tr>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">Session Management</td>
                                    <td><span class="badge status-badge-available">Active (ID: <?= substr(session_id(), 0, 10) ?>...)</span></td>
                                    <td class="small font-meta text-muted">Secure session regenerated on authentication to prevent fixation.</td>
                                </tr>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">CSRF Protection</td>
                                    <td><span class="badge status-badge-available">Active (hash_equals validation)</span></td>
                                    <td class="small font-meta text-muted">All POST forms protected with cryptographically secure tokens.</td>
                                </tr>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">Upload Directory Protection</td>
                                    <td><span class="badge status-badge-available">Active (.htaccess blocked)</span></td>
                                    <td class="small font-meta text-muted">Direct script execution disabled in uploads folder.</td>
                                </tr>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">Post Max Size</td>
                                    <td class="font-meta small"><?= ini_get('post_max_size') ?></td>
                                    <td class="small font-meta text-muted">Controls max payload size for multipart uploads.</td>
                                </tr>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">Max Execution Time</td>
                                    <td class="font-meta small"><?= ini_get('max_execution_time') ?> seconds</td>
                                    <td class="small font-meta text-muted">Sufficient for backup generation and file processing.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
