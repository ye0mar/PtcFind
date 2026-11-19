<?php
/**
 * Found Items Inventory (Browse & Search)
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = get_db_connection();

// Filters & Search
$search = trim($_GET['search'] ?? '');
$categoryId = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)get_setting('items_per_page', '12'));

// Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(f.item_name LIKE :s OR f.description LIKE :s OR f.location_found LIKE :s OR f.storage_location LIKE :s)";
    $params[':s'] = "%$search%";
}

if (!empty($categoryId) && is_numeric($categoryId)) {
    $where[] = "f.category_id = :cat";
    $params[':cat'] = $categoryId;
}

if (!empty($status) && in_array($status, ['available', 'matched', 'returned', 'closed'])) {
    $where[] = "f.status = :st";
    $params[':st'] = $status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM found_items f $whereClause");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch found items
$query = "SELECT f.*, c.name AS category_name, u.full_name AS recorder_name
          FROM found_items f
          LEFT JOIN categories c ON f.category_id = c.id
          LEFT JOIN users u ON f.recorded_by = u.id
          $whereClause
          ORDER BY f.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$pageTitle = 'Found Items Inventory';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid">
            <!-- Header bar -->
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-semibold mb-1">Found Items Inventory</h1>
                    <p class="font-meta text-muted small mb-0">Custody records of recovered belongings kept in campus security lockers.</p>
                </div>
                <a href="<?= base_url('found_items/add.php') ?>" class="btn btn-primary px-3 py-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Record Found Item</span>
                </a>
            </div>

            <?= render_flash() ?>

            <!-- Filter Card -->
            <div class="card mb-4">
                <div class="card-body p-3 p-md-4">
                    <form method="GET" action="<?= base_url('found_items/index.php') ?>" class="row g-2 g-md-3 align-items-center">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search item, location found, storage locker..." value="<?= sanitize($search) ?>">
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <select name="category" class="form-select bg-light">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($categoryId == $cat['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <select name="status" class="form-select bg-light">
                                <option value="">All Statuses</option>
                                <option value="available" <?= ($status === 'available') ? 'selected' : '' ?>>Available</option>
                                <option value="matched" <?= ($status === 'matched') ? 'selected' : '' ?>>Matched</option>
                                <option value="returned" <?= ($status === 'returned') ? 'selected' : '' ?>>Returned</option>
                                <option value="closed" <?= ($status === 'closed') ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <span>Filter</span>
                            </button>
                            <a href="<?= base_url('found_items/index.php') ?>" class="btn btn-secondary" title="Reset Filters">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card overflow-hidden">
                <div class="card-body p-0">
                    <?php if (empty($items)): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-inbox fs-1 text-muted"></i>
                            <h5 class="font-display fw-semibold text-dark mt-3">No found items in storage</h5>
                            <p class="font-meta text-muted small mb-0">Record an item handed in by security or campus staff.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Item Details</th>
                                        <th>Category</th>
                                        <th>Location Found</th>
                                        <th>Storage Location</th>
                                        <th>Date Found</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($item['image_path']) && item_image_exists($item['image_path'])): ?>
                                                        <img src="<?= base_url($item['image_path']) ?>" alt="<?= sanitize($item['item_name']) ?>" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="fa-solid fa-box-open">
                                                    <?php else: ?>
                                                        <div class="item-thumb-placeholder">
                                                            <i class="fa-solid fa-box-open"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="<?= base_url('found_items/view.php?id=' . $item['id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                                            <?= sanitize($item['item_name']) ?>
                                                        </a>
                                                        <p class="font-meta text-muted small text-truncate mb-0" style="max-width: 240px;">
                                                            <?= sanitize($item['description']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="category-badge"><?= sanitize($item['category_name'] ?? 'General') ?></span></td>
                                            <td class="small font-meta text-muted">
                                                <i class="fa-solid fa-location-dot me-1 text-secondary"></i><?= sanitize($item['location_found']) ?>
                                            </td>
                                            <td>
                                                <span class="storage-badge">
                                                    <i class="fa-solid fa-box-archive"></i>
                                                    <span><?= sanitize($item['storage_location'] ?? 'Unassigned') ?></span>
                                                </span>
                                            </td>
                                            <td class="small font-meta text-muted text-nowrap"><?= format_date($item['date_found']) ?></td>
                                            <td>
                                                <span class="badge status-badge-<?= sanitize($item['status']) ?>">
                                                    <?= strtoupper(sanitize($item['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <a href="<?= base_url('found_items/view.php?id=' . $item['id']) ?>" class="btn btn-view btn-action-icon" title="View details">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                    <a href="<?= base_url('found_items/edit.php?id=' . $item['id']) ?>" class="btn btn-outline-primary btn-action-icon" title="Edit record">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <form method="POST" action="<?= base_url('found_items/process.php') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this found item record?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-action-icon" title="Delete record">
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

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between">
                                <span class="small font-meta text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalItems ?> items)</span>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= base_url('found_items/index.php?page=' . ($page - 1) . '&search=' . urlencode($search) . '&category=' . urlencode($categoryId) . '&status=' . urlencode($status)) ?>">Previous</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= base_url('found_items/index.php?page=' . $p . '&search=' . urlencode($search) . '&category=' . urlencode($categoryId) . '&status=' . urlencode($status)) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= base_url('found_items/index.php?page=' . ($page + 1) . '&search=' . urlencode($search) . '&category=' . urlencode($categoryId) . '&status=' . urlencode($status)) ?>">Next</a>
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
