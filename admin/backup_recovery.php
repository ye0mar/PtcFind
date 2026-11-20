<?php
/**
 * Database Backup & Recovery
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
$user = current_user();

// Handle Export SQL Download
if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $tables = ['users', 'categories', 'lost_items', 'found_items', 'matches', 'activity_logs', 'system_settings', 'backup_logs'];
    $sqlDump = "-- =========================================================\n";
    $sqlDump .= "-- FoundU Database Backup\n";
    $sqlDump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Operator: " . $user['username'] . "\n";
    $sqlDump .= "-- =========================================================\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $sqlDump .= "-- ---------------------------------------------------------\n";
        $sqlDump .= "-- Table structure for `$table`\n";
        $sqlDump .= "-- ---------------------------------------------------------\n";

        try {
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            if ($createTable) {
                $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
                $sqlDump .= $createTable[1] . ";\n\n";
            }

            // Dump Data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $sqlDump .= "-- Dumping data for `$table`\n";
                foreach ($rows as $row) {
                    $cols = array_map(function($c) { return "`$c`"; }, array_keys($row));
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? "NULL" : $pdo->quote($v);
                    }, array_values($row));
                    $sqlDump .= "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                }
                $sqlDump .= "\n";
            }
        } catch (Exception $e) {
            $sqlDump .= "-- Could not dump table `$table`: " . $e->getMessage() . "\n\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = 'foundu_backup_' . date('Y-m-d_His') . '.sql';
    $size = strlen($sqlDump);

    // Record in backup_logs
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO backup_logs (user_id, filename, size_bytes, action, status, notes, created_at)
             VALUES (:uid, :fn, :sz, 'backup', 'success', 'Manual SQL export by admin', NOW())"
        );
        $stmt->execute([
            ':uid' => $user['id'],
            ':fn'  => $filename,
            ':sz'  => $size,
        ]);
        log_activity('EXPORT_BACKUP', 'backup_logs', (int)$pdo->lastInsertId(), "Generated database backup {$filename} ({$size} bytes)");
    } catch (Exception $e) {
        // Non critical
    }

    // Force browser download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    echo $sqlDump;
    exit;
}

// Fetch backup history
$backups = $pdo->query(
    "SELECT b.*, u.full_name AS operator_name
     FROM backup_logs b
     LEFT JOIN users u ON b.user_id = u.id
     ORDER BY b.created_at DESC"
)->fetchAll();

$pageTitle = 'Database Backup & Recovery';
$extraCss = ['assets/css/dashboard.css'];
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
                    <h1 class="h3 fw-semibold mb-1">Database Backup &amp; Recovery</h1>
                    <p class="font-meta text-muted small mb-0">Export live database SQL snapshots or restore previous backups in XAMPP phpMyAdmin.</p>
                </div>
            </div>

            <?= render_flash() ?>

            <!-- Action Cards -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="stat-icon-wrapper stat-icon-blue">
                                <i class="fa-solid fa-download"></i>
                            </div>
                            <div>
                                <h5 class="font-display fw-semibold mb-1">Export Database SQL</h5>
                                <small class="font-meta text-muted">Create an instant .sql snapshot of all items, users, and logs.</small>
                            </div>
                        </div>
                        <p class="small text-secondary mb-4">
                            Generates a complete SQL file containing all table structures and rows. You can import this file directly into MySQL or phpMyAdmin in XAMPP at any time.
                        </p>
                        <a href="<?= base_url('admin/backup_recovery.php?download=sql') ?>" class="btn btn-primary w-100 py-2 mt-auto">
                            <i class="fa-solid fa-cloud-arrow-down"></i>
                            <span>Download Full SQL Dump</span>
                        </a>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100 p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="stat-icon-wrapper stat-icon-secondary">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </div>
                            <div>
                                <h5 class="font-display fw-semibold mb-1">Restore Instructions (XAMPP)</h5>
                                <small class="font-meta text-muted">How to restore a database backup in XAMPP.</small>
                            </div>
                        </div>
                        <ol class="small text-secondary ps-3 mb-0" style="line-height: 1.8;">
                            <li>Open your browser and navigate to <strong>http://localhost/phpmyadmin/</strong>.</li>
                            <li>Click on the database <strong class="font-meta">lost_and_found_db1</strong> in the left sidebar.</li>
                            <li>Click on the <strong>Import</strong> tab at the top.</li>
                            <li>Choose your exported <strong class="font-meta">.sql</strong> backup file and click <strong>Import</strong>.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Backup History Table -->
            <div class="card overflow-hidden">
                <div class="card-header py-3 px-4">
                    <h5 class="font-display fw-semibold mb-0 fs-6">Backup Logs History</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($backups)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clock-rotate-left fs-1"></i>
                            <p class="font-meta mt-2 small mb-0">No backups logged yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">ID</th>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th>Action</th>
                                        <th>Status</th>
                                        <th>Operator</th>
                                        <th class="pe-4 text-end">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $b): ?>
                                        <tr>
                                            <td class="ps-4 font-meta text-muted small">#<?= $b['id'] ?></td>
                                            <td class="font-meta fw-semibold text-dark"><?= sanitize($b['filename']) ?></td>
                                            <td class="small font-meta text-muted"><?= number_format($b['size_bytes'] / 1024, 1) ?> KB</td>
                                            <td class="small font-meta text-uppercase fw-bold text-muted"><?= sanitize($b['action']) ?></td>
                                            <td>
                                                <span class="badge status-badge-available">
                                                    <?= strtoupper(sanitize($b['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="small fw-medium text-secondary"><?= sanitize($b['operator_name'] ?? 'Admin') ?></td>
                                            <td class="pe-4 text-end small font-meta text-muted text-nowrap"><?= format_datetime($b['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
