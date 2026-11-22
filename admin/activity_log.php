<?php
/**
 * Activity Log Audit Trail
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(a.action LIKE :s1 OR a.details LIKE :s2 OR u.username LIKE :s3 OR u.full_name LIKE :s4 OR a.ip_address LIKE :s5)";
    $likeTerm = "%$search%";
    $params[':s1'] = $likeTerm;
    $params[':s2'] = $likeTerm;
    $params[':s3'] = $likeTerm;
    $params[':s4'] = $likeTerm;
    $params[':s5'] = $likeTerm;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id $whereClause");
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch logs
$query = "SELECT a.*, u.username, u.full_name
          FROM activity_logs a
          LEFT JOIN users u ON a.user_id = u.id
          $whereClause
          ORDER BY a.created_at DESC, a.id DESC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

function render_activity_log_results(array $logs, int $page, int $totalPages, int $totalLogs, string $search): string
{
    ob_start();
    ?>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted px-3">
                <i class="fa-solid fa-clock-rotate-left fs-1"></i>
                <h6 class="font-display fw-semibold text-dark mt-3 mb-1">
                    <?= $search !== '' ? 'No matching activity logs found' : 'No activity logs recorded yet' ?>
                </h6>
                <p class="font-meta small mb-0">
                    <?= $search !== ''
                        ? 'No records matched "' . sanitize($search) . '". Try another keyword or clear the search input.'
                        : 'System logins, item changes, and administrative actions will appear here.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Operator</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th class="pe-4 text-end">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="ps-4 font-meta text-muted small">#<?= $l['id'] ?></td>
                                <td>
                                    <?php if ($l['username']): ?>
                                        <span class="fw-semibold text-dark"><?= sanitize($l['full_name']) ?></span>
                                        <small class="font-meta text-muted d-block">@<?= sanitize($l['username']) ?></small>
                                    <?php else: ?>
                                        <span class="category-badge">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge role-badge-staff font-meta">
                                        <?= sanitize($l['action']) ?>
                                    </span>
                                </td>
                                <td class="small font-meta text-muted">
                                    <?= sanitize($l['target_type'] ?: '—') ?>
                                    <?= $l['target_id'] ? '#' . $l['target_id'] : '' ?>
                                </td>
                                <td class="small text-dark text-break" style="max-width: 320px;">
                                    <?= sanitize($l['details']) ?>
                                </td>
                                <td class="small font-meta text-muted"><?= sanitize($l['ip_address']) ?></td>
                                <td class="pe-4 text-end small font-meta text-muted text-nowrap"><?= format_datetime($l['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="card-footer py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="small font-meta text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalLogs ?> records)</span>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= base_url('admin/activity_log.php?page=' . ($page - 1) . '&search=' . urlencode($search)) ?>">Previous</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= base_url('admin/activity_log.php?page=' . $p . '&search=' . urlencode($search)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= base_url('admin/activity_log.php?page=' . ($page + 1) . '&search=' . urlencode($search)) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

$resultsHtml = render_activity_log_results($logs, $page, $totalPages, $totalLogs, $search);

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'         => true,
        'search'     => $search,
        'page'       => $page,
        'totalPages' => $totalPages,
        'totalLogs'  => $totalLogs,
        'html'       => $resultsHtml,
    ]);
    exit;
}

$pageTitle = 'Activity Logs';
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
                    <h1 class="h3 fw-semibold mb-1">System Activity Logs</h1>
                    <p class="font-meta text-muted small mb-0">Immutable audit trail of operator logins, item registries, status updates, and administrative actions.</p>
                </div>
            </div>

            <!-- Compact Live Search Input -->
            <div class="card mb-4">
                <div class="card-body p-3 p-md-4">
                    <form method="GET" action="<?= base_url('admin/activity_log.php') ?>" class="live-search-form" data-live-search data-results-target="#activityLogResults">
                        <div class="live-search-box" data-live-search-box>
                            <i class="fa-solid fa-magnifying-glass live-search-icon" aria-hidden="true"></i>
                            <input
                                type="text"
                                name="search"
                                class="form-control live-search-input"
                                placeholder="Search by action, details, operator, or IP address..."
                                value="<?= sanitize($search) ?>"
                                autocomplete="off"
                                aria-label="Search activity logs"
                            >
                            <div class="live-search-actions">
                                <span class="live-search-spinner" aria-hidden="true">
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                </span>
                                <button
                                    type="button"
                                    class="live-search-clear <?= $search !== '' ? '' : 'd-none' ?>"
                                    data-live-search-clear
                                    aria-label="Clear search"
                                    title="Clear search"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Log Table -->
            <div id="activityLogResults" class="card overflow-hidden live-search-results" data-live-search-results>
                <?= $resultsHtml ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
