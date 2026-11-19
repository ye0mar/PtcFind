<?php
/**
 * Notifications JSON API (Polling & Read-State Actions)
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.']);
    exit;
}

$pdo = get_db_connection();
$userId = (int)$user['id'];
ensure_notifications_table($pdo);

function format_notification_payload(array $n): array {
    $itemType = $n['related_item_type'] === 'found_item' ? 'found_item' : 'lost_item';
    $senderRole = $n['sender_role'] === 'admin' ? 'admin' : 'staff';
    $id = (int)$n['id'];
    return [
        'id'                 => $id,
        'title'              => (string)$n['title'],
        'message'            => (string)$n['message'],
        'sender_name'        => (string)($n['sender_name'] ?? 'Operator'),
        'sender_role'        => $senderRole,
        'related_item_id'    => (int)$n['related_item_id'],
        'related_item_type'  => $itemType,
        'related_item_label' => $itemType === 'found_item' ? 'Found Item' : 'Lost Item',
        'related_item_name'  => (string)($n['related_item_name'] ?? ('Item #' . (int)$n['related_item_id'])),
        'item_exists'        => (bool)($n['item_exists'] ?? false),
        'is_read'            => ((int)$n['is_read']) === 1,
        'created_at'         => (string)$n['created_at'],
        'created_at_formatted' => format_datetime((string)$n['created_at']),
        'open_url'           => base_url('notifications/index.php?open=' . $id),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validate_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid or expired security token.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid notification ID.']);
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT id, recipient_user_id FROM notifications WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $notifId]);
        $row = $checkStmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Notification not found.']);
            exit;
        }

        if ((int)$row['recipient_user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'You do not have permission to modify this notification.']);
            exit;
        }

        mark_notification_read($pdo, $notifId, $userId);
        $unreadCount = get_unread_notification_count($userId);

        echo json_encode([
            'ok'          => true,
            'unreadCount' => $unreadCount,
        ]);
        exit;
    }

    if ($action === 'mark_all_read') {
        mark_all_notifications_read($pdo, $userId);
        echo json_encode([
            'ok'          => true,
            'unreadCount' => 0,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unsupported action.']);
    exit;
}

// GET polling
$lastId = max(0, (int)($_GET['last_id'] ?? 0));
$includeList = ($_GET['include_list'] ?? '0') === '1';

$unreadCount = get_unread_notification_count($userId);

$maxStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM notifications WHERE recipient_user_id = :uid");
$maxStmt->execute([':uid' => $userId]);
$latestId = (int)$maxStmt->fetchColumn();

$newNotifications = [];
if ($lastId > 0 && $latestId > $lastId) {
    $rows = get_user_notifications($pdo, $userId, 15, $lastId);
    foreach ($rows as $r) {
        $newNotifications[] = format_notification_payload($r);
    }
}

$response = [
    'ok'               => true,
    'userId'           => $userId,
    'unreadCount'      => $unreadCount,
    'latestId'         => $latestId,
    'newNotifications' => $newNotifications,
];

if ($includeList) {
    $allRows = get_user_notifications($pdo, $userId, 100, 0);
    $response['notifications'] = array_map('format_notification_payload', $allRows);
}

echo json_encode($response);
