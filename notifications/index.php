<?php
/**
 * Notifications Center Page
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash_messages.php';
require_once __DIR__ . '/../includes/notifications.php';

$pdo = get_db_connection();
$user = current_user();
$userId = (int)$user['id'];
ensure_notifications_table($pdo);

// Handle direct click-to-open (?open=notification_id): marks as read then navigates to item view
if (isset($_GET['open'])) {
    $openId = (int)$_GET['open'];
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $openId]);
    $notif = $stmt->fetch();

    if (!$notif) {
        set_flash('error', 'Notification not found.');
        header('Location: ' . base_url('notifications/index.php'));
        exit;
    }

    if ((int)$notif['recipient_user_id'] !== $userId) {
        set_flash('error', 'You do not have permission to open that notification.');
        header('Location: ' . base_url('notifications/index.php'));
        exit;
    }

    mark_notification_read($pdo, $openId, $userId);
    header('Location: ' . get_notification_item_url($notif));
    exit;
}

// Handle standard POST fallback actions (Mark as Read / Mark All as Read)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            mark_notification_read($pdo, $notifId, $userId);
        }
    } elseif ($action === 'mark_all_read') {
        mark_all_notifications_read($pdo, $userId);
        set_flash('success', 'All notifications marked as read.');
    }

    header('Location: ' . base_url('notifications/index.php'));
    exit;
}

$notifications = get_user_notifications($pdo, $userId, 100, 0);
$unreadCount = get_unread_notification_count($userId);
$latestId = 0;
foreach ($notifications as $n) {
    if ((int)$n['id'] > $latestId) {
        $latestId = (int)$n['id'];
    }
}

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex flex-grow-1 flex-column flex-lg-row">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
        <div class="container-fluid" style="max-width: 960px;" data-notifications-page data-latest-id="<?= $latestId ?>">
            <!-- Header Bar -->
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h1 class="h3 fw-semibold mb-0">Notifications</h1>
                        <span class="badge <?= $unreadCount > 0 ? 'status-badge-matched' : 'status-badge-inactive' ?>" id="pageUnreadBadge">
                            <span data-page-unread-count><?= $unreadCount ?></span> Unread
                        </span>
                    </div>
                    <p class="font-meta text-muted small mb-0">
                        Role-based alerts for newly reported lost items and found custody records.
                    </p>
                </div>

                <form method="POST" action="<?= base_url('notifications/index.php') ?>" class="d-inline" data-mark-all-read-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit"
                            class="btn btn-outline-primary btn-sm px-3 py-2<?= $unreadCount === 0 ? ' disabled' : '' ?>"
                            data-mark-all-read-btn
                            <?= $unreadCount === 0 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-check-double"></i>
                        <span>Mark All as Read</span>
                    </button>
                </form>
            </div>

            <?= render_flash() ?>

            <!-- Notifications List -->
            <div id="notificationsListContainer">
                <?php if (empty($notifications)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div class="stat-icon-wrapper stat-icon-primary mx-auto mb-3">
                                <i class="fa-regular fa-bell"></i>
                            </div>
                            <h5 class="font-display fw-semibold text-dark mb-1">No notifications yet</h5>
                            <p class="font-meta text-muted small mb-0">
                                <?= $user['role'] === 'admin'
                                    ? 'You will be notified automatically whenever staff operators add a new Lost, Found, or Returned Item.'
                                    : 'You will be notified automatically whenever administrators add a new Lost, Found, or Returned Item.' ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notif): ?>
                            <?php
                                $isRead = ((int)$notif['is_read']) === 1;
                                $itemType = (string)($notif['related_item_type'] ?? 'lost_item');
                                if ($itemType === 'found_item') {
                                    $itemLabel = 'Found Item';
                                    $itemIcon = 'fa-box-open';
                                } elseif ($itemType === 'returned_item') {
                                    $itemLabel = 'Returned Item';
                                    $itemIcon = 'fa-clipboard-check';
                                } else {
                                    $itemLabel = 'Lost Item';
                                    $itemIcon = 'fa-circle-question';
                                }
                                $senderRole = ($notif['sender_role'] === 'admin') ? 'admin' : 'staff';
                                $senderName = $notif['sender_name'] ?? 'Operator';
                                $itemName = $notif['related_item_name'] ?? ('Item #' . (int)$notif['related_item_id']);
                            ?>
                            <div class="notification-card <?= $isRead ? 'is-read' : 'is-unread' ?>" data-notification-id="<?= (int)$notif['id'] ?>">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                    <div class="d-flex align-items-start gap-3 min-w-0">
                                        <div class="notification-icon-wrap">
                                            <i class="fa-solid <?= $itemIcon ?>"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                <?php if (!$isRead): ?>
                                                    <span class="notification-unread-dot" title="Unread" data-unread-dot></span>
                                                <?php endif; ?>
                                                <h6 class="font-display fw-semibold text-dark mb-0"><?= sanitize($notif['title']) ?></h6>
                                                <span class="badge role-badge-<?= $senderRole ?>">
                                                    <?= strtoupper($senderRole) ?>
                                                </span>
                                                <span class="notification-type-badge">
                                                    <i class="fa-solid <?= $itemIcon ?>"></i>
                                                    <span><?= $itemLabel ?></span>
                                                </span>
                                            </div>
                                            <p class="small text-dark mb-2">
                                                <?= sanitize($notif['message']) ?>
                                                <span class="text-muted">&middot; Item: <strong class="text-dark"><?= sanitize($itemName) ?></strong></span>
                                            </p>
                                            <div class="d-flex flex-wrap align-items-center gap-3 small font-meta text-muted">
                                                <span><i class="fa-solid fa-user me-1"></i><?= sanitize($senderName) ?></span>
                                                <span><i class="fa-regular fa-clock me-1"></i><?= format_datetime($notif['created_at']) ?></span>
                                                <span class="fw-semibold <?= $isRead ? 'text-muted' : 'text-primary' ?>" data-read-status-label>
                                                    <?= $isRead ? 'Read' : 'Unread' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                                        <a href="<?= base_url('notifications/index.php?open=' . (int)$notif['id']) ?>" class="btn btn-soft-primary btn-sm px-3">
                                            <i class="fa-solid fa-eye"></i>
                                            <span>View Item</span>
                                        </a>
                                        <?php if (!$isRead): ?>
                                            <form method="POST" action="<?= base_url('notifications/index.php') ?>" class="d-inline" data-mark-read-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm px-3" data-mark-read-btn="<?= (int)$notif['id'] ?>">
                                                    <i class="fa-solid fa-check"></i>
                                                    <span>Mark Read</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
