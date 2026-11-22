<?php
/**
 * Returned Items Directory (Browse & Search Handover Log)
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();
ensure_returned_items_table($pdo);

// Filters & Search
$search = trim($_GET['search'] ?? '');
$categoryId = trim($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)get_setting('items_per_page', '12'));

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(r.item_name LIKE :s1 OR r.description LIKE :s2 OR r.receiver_name LIKE :s3 OR r.receiver_contact LIKE :s4)";
    $likeTerm = "%$search%";
    $params[':s1'] = $likeTerm;
    $params[':s2'] = $likeTerm;
    $params[':s3'] = $likeTerm;
    $params[':s4'] = $likeTerm;
}

if ($categoryId !== '' && is_numeric($categoryId)) {
    $where[] = "r.category_id = :cat";
    $params[':cat'] = (int)$categoryId;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM returned_items r $whereClause");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch returned items
$query = "SELECT r.*,
                 c.name AS category_name,
                 u.full_name AS recorder_name,
                 l.item_name AS linked_lost_name,
                 f.item_name AS linked_found_name
          FROM returned_items r
          LEFT JOIN categories c ON r.category_id = c.id
          LEFT JOIN users u ON r.recorded_by = u.id
          LEFT JOIN lost_items l ON r.lost_item_id = l.id
          LEFT JOIN found_items f ON r.found_item_id = f.id
          $whereClause
          ORDER BY r.date_returned DESC, r.created_at DESC, r.id DESC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$pageTitle = 'Returned Items';
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
                    <h1 class="h3 fw-semibold mb-1">Returned Items</h1>
                    <p class="font-meta text-muted small mb-0">Official log of items returned to their rightful owners with receiver details and photographic proof.</p>
                </div>
                <a href="<?= base_url('returned_items/add.php') ?>" class="btn btn-primary px-3 py-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Record Returned Item</span>
                </a>
            </div>

            <?= render_flash() ?>

            <!-- Filter Card -->
            <div class="card mb-4">
                <div class="card-body p-3 p-md-4">
                    <form method="GET" action="<?= base_url('returned_items/index.php') ?>" class="row g-2 g-md-3 align-items-center">
                        <div class="col-12 col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search item name, receiver name, contact..." value="<?= sanitize($search) ?>">
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <select name="category" class="form-select bg-light">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($categoryId == $cat['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <span>Filter</span>
                            </button>
                            <a href="<?= base_url('returned_items/index.php') ?>" class="btn btn-secondary" title="Reset Filters">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Returned Items Table -->
            <div class="card overflow-hidden">
                <div class="card-body p-0">
                    <?php if (empty($items)): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-clipboard-check fs-1 text-muted"></i>
                            <h5 class="font-display fw-semibold text-dark mt-3">No returned items recorded yet</h5>
                            <p class="font-meta text-muted small mb-0">Record a handover with the receiver's name, return date, and proof photo.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Item Details</th>
                                        <th>Category</th>
                                        <th>Received By</th>
                                        <th>Date Returned</th>
                                        <th>Proof Photo</th>
                                        <th>Recorded By</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($item['item_image_path']) && item_image_exists($item['item_image_path'])): ?>
                                                        <img src="<?= base_url($item['item_image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-solid fa-clipboard-check">
                                                    <?php else: ?>
                                                        <div class="item-thumb-placeholder">
                                                            <i class="fa-solid fa-clipboard-check"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="<?= base_url('returned_items/view.php?id=' . (int)$item['id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                                            <?= sanitize($item['item_name']) ?>
                                                        </a>
                                                        <?php if (!empty($item['description'])): ?>
                                                            <p class="font-meta text-muted small text-truncate mb-0" style="max-width: 240px;">
                                                                <?= sanitize($item['description']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['lost_item_id']) || !empty($item['found_item_id'])): ?>
                                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                                <?php if (!empty($item['lost_item_id'])): ?>
                                                                    <span class="badge status-badge-claimed" style="font-size: 0.65rem;">Lost #<?= (int)$item['lost_item_id'] ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['found_item_id'])): ?>
                                                                    <span class="badge status-badge-available" style="font-size: 0.65rem;">Found #<?= (int)$item['found_item_id'] ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="category-badge"><?= sanitize($item['category_name'] ?? 'General') ?></span></td>
                                            <td>
                                                <div class="fw-semibold text-dark small"><?= sanitize($item['receiver_name']) ?></div>
                                                <?php if (!empty($item['receiver_contact'])): ?>
                                                    <div class="font-meta text-muted small"><?= sanitize($item['receiver_contact']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small font-meta text-muted text-nowrap"><?= format_date($item['date_returned']) ?></td>
                                            <td>
                                                <?php if (!empty($item['proof_image_path']) && item_image_exists($item['proof_image_path'])): ?>
                                                    <a href="<?= base_url('returned_items/view.php?id=' . (int)$item['id']) ?>" title="View Proof Photo">
                                                        <img src="<?= base_url($item['proof_image_path']) ?>" alt="Proof photo for <?= sanitize($item['item_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-solid fa-camera">
                                                    </a>
                                                <?php else: ?>
                                                    <div class="item-thumb-placeholder" title="Proof photo unavailable">
                                                        <i class="fa-solid fa-camera"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small font-meta text-muted"><?= sanitize($item['recorder_name'] ?? 'Staff') ?></td>
                                            <td class="text-end pe-4">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <a href="<?= base_url('returned_items/view.php?id=' . (int)$item['id']) ?>" class="btn btn-view btn-action-icon" title="View return details">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                    <form method="POST" action="<?= base_url('returned_items/process.php') ?>" class="d-inline" data-confirm="Delete this returned item record?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-action-icon" title="Delete return record">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between">
                                <span class="small font-meta text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalItems ?> records)</span>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= base_url('returned_items/index.php?page=' . ($page - 1) . '&search=' . urlencode($search) . '&category=' . urlencode($categoryId)) ?>">Previous</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= base_url('returned_items/index.php?page=' . $p . '&search=' . urlencode($search) . '&category=' . urlencode($categoryId)) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= base_url('returned_items/index.php?page=' . ($page + 1) . '&search=' . urlencode($search) . '&category=' . urlencode($categoryId)) ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
