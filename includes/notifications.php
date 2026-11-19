<?php
/**
 * Role-Based Notifications Helper Functions
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/functions.php';

/**
 * Ensure the notifications table exists (safe idempotent DDL for existing databases).
 */
function ensure_notifications_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `notifications` (
          `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `recipient_user_id` INT(11) UNSIGNED NOT NULL,
          `sender_user_id` INT(11) UNSIGNED NOT NULL,
          `sender_role` ENUM('admin','staff') NOT NULL,
          `related_item_id` INT(11) UNSIGNED NOT NULL,
          `related_item_type` ENUM('lost_item','found_item') NOT NULL,
          `title` VARCHAR(160) NOT NULL,
          `message` TEXT NOT NULL,
          `is_read` TINYINT(1) NOT NULL DEFAULT 0,
          `read_at` DATETIME DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_notification_recipient_item` (`recipient_user_id`, `sender_user_id`, `related_item_type`, `related_item_id`),
          KEY `idx_recipient_read` (`recipient_user_id`, `is_read`, `created_at`),
          KEY `idx_sender_user` (`sender_user_id`),
          CONSTRAINT `fk_notif_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $checked = true;
}

/**
 * Create role-based notifications when a Lost Item or Found Item is created.
 * STAFF -> notifies active ADMIN accounts.
 * ADMIN -> notifies active STAFF accounts.
 * Never notifies the sender and prevents duplicate notifications.
 */
function create_role_item_notifications(
    PDO $pdo,
    int $senderUserId,
    string $relatedItemType,
    int $relatedItemId,
    string $itemName = ''
): int {
    ensure_notifications_table($pdo);

    if (!in_array($relatedItemType, ['lost_item', 'found_item'], true) || $senderUserId <= 0 || $relatedItemId <= 0) {
        return 0;
    }

    // Fetch authoritative sender account details from DB
    $senderStmt = $pdo->prepare("SELECT id, full_name, role, status FROM users WHERE id = :id LIMIT 1");
    $senderStmt->execute([':id' => $senderUserId]);
    $sender = $senderStmt->fetch();

    if (!$sender || $sender['status'] !== 'active') {
        return 0;
    }

    $senderRole = $sender['role'] === 'admin' ? 'admin' : 'staff';
    $targetRole = ($senderRole === 'staff') ? 'admin' : 'staff';
    $senderName = trim((string)$sender['full_name']) ?: ($senderRole === 'admin' ? 'System Administrator' : 'Campus Staff Officer');

    // Find all active recipients of the opposite role (excluding sender)
    $recStmt = $pdo->prepare(
        "SELECT id FROM users WHERE role = :target_role AND status = 'active' AND id != :sender_id"
    );
    $recStmt->execute([
        ':target_role' => $targetRole,
        ':sender_id'   => $senderUserId,
    ]);
    $recipients = $recStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        return 0;
    }

    $itemLabel = ($relatedItemType === 'lost_item') ? 'Lost Item' : 'Found Item';
    $title = 'New Item Added';
    $message = "{$senderName} has added a new {$itemLabel}.";

    $insertStmt = $pdo->prepare(
        "INSERT IGNORE INTO notifications
         (recipient_user_id, sender_user_id, sender_role, related_item_id, related_item_type, title, message, is_read, created_at)
         VALUES (:recipient_id, :sender_id, :sender_role, :item_id, :item_type, :title, :message, 0, NOW())"
    );

    $created = 0;
    foreach ($recipients as $recipientId) {
        $ok = $insertStmt->execute([
            ':recipient_id' => (int)$recipientId,
            ':sender_id'    => $senderUserId,
            ':sender_role'  => $senderRole,
            ':item_id'      => $relatedItemId,
            ':item_type'    => $relatedItemType,
            ':title'        => $title,
            ':message'      => $message,
        ]);
        if ($ok && $insertStmt->rowCount() > 0) {
            $created++;
        }
    }

    return $created;
}

/**
 * Count unread notifications for a specific user.
 */
function get_unread_notification_count(int $userId): int {
    if ($userId <= 0) {
        return 0;
    }
    try {
        $pdo = get_db_connection();
        ensure_notifications_table($pdo);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Fetch notifications belonging exclusively to $userId (newest first).
 */
function get_user_notifications(PDO $pdo, int $userId, int $limit = 100, int $afterId = 0): array {
    ensure_notifications_table($pdo);

    $sql = "SELECT n.*,
                   u.full_name AS sender_name,
                   u.username AS sender_username,
                   COALESCE(l.item_name, f.item_name) AS related_item_name,
                   CASE
                       WHEN n.related_item_type = 'lost_item' THEN (l.id IS NOT NULL)
                       WHEN n.related_item_type = 'found_item' THEN (f.id IS NOT NULL)
                       ELSE 0
                   END AS item_exists
            FROM notifications n
            LEFT JOIN users u ON n.sender_user_id = u.id
            LEFT JOIN lost_items l ON (n.related_item_type = 'lost_item' AND n.related_item_id = l.id)
            LEFT JOIN found_items f ON (n.related_item_type = 'found_item' AND n.related_item_id = f.id)
            WHERE n.recipient_user_id = :uid";

    if ($afterId > 0) {
        $sql .= " AND n.id > :after_id";
    }

    $sql .= " ORDER BY n.created_at DESC, n.id DESC LIMIT :lim";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    if ($afterId > 0) {
        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Mark a single notification as read only if it belongs to $userId.
 */
function mark_notification_read(PDO $pdo, int $notificationId, int $userId): bool {
    ensure_notifications_table($pdo);
    if ($notificationId <= 0 || $userId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare(
        "UPDATE notifications
         SET is_read = 1, read_at = COALESCE(read_at, NOW())
         WHERE id = :id AND recipient_user_id = :uid"
    );
    $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Mark all notifications for $userId as read.
 */
function mark_all_notifications_read(PDO $pdo, int $userId): int {
    ensure_notifications_table($pdo);
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "UPDATE notifications
         SET is_read = 1, read_at = NOW()
         WHERE recipient_user_id = :uid AND is_read = 0"
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->rowCount();
}

/**
 * Resolve item detail URL for a notification.
 */
function get_notification_item_url(array $notification): string {
    $itemId = (int)($notification['related_item_id'] ?? 0);
    if (($notification['related_item_type'] ?? '') === 'lost_item') {
        return base_url('lost_items/view.php?id=' . $itemId);
    }
    return base_url('found_items/view.php?id=' . $itemId);
}
