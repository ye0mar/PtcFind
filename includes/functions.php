<?php
/**
 * Global Utility Functions
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize string output for safe HTML display.
 */
function sanitize(?string $data): string {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date string into human-readable format.
 */
function format_date(?string $dateStr, string $format = 'M d, Y'): string {
    if (empty($dateStr)) return '—';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : $dateStr;
}

/**
 * Format datetime string into human-readable format.
 */
function format_datetime(?string $dateTimeStr, string $format = 'M d, Y h:i A'): string {
    if (empty($dateTimeStr)) return '—';
    $timestamp = strtotime($dateTimeStr);
    return $timestamp ? date($format, $timestamp) : $dateTimeStr;
}

/**
 * Log activity in activity_logs table.
 */
function log_activity(string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): bool {
    try {
        $pdo = get_db_connection();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, action, target_type, target_id, details, ip_address, created_at)
             VALUES (:user_id, :action, :target_type, :target_id, :details, :ip, NOW())"
        );
        return $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':target_type' => $targetType,
            ':target_id'   => $targetId,
            ':details'     => $details,
            ':ip'          => $ip,
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve a system setting from system_settings table.
 */
function get_setting(string $key, string $default = ''): string {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Retrieve base application URL dynamically.
 */
function base_url(string $path = ''): string {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Normalize path separators on Windows (XAMPP)
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    $subfolders = ['lost_items', 'found_items', 'matches', 'admin', 'profile', 'setup', 'notifications'];
    $root = rtrim($scriptDir, '/');
    if (preg_match('#/(' . implode('|', $subfolders) . ')$#', $root)) {
        $root = preg_replace('#/(' . implode('|', $subfolders) . ')$#', '', $root);
    }
    
    $cleanPath = ltrim($path, '/');
    return rtrim($root, '/') . ($cleanPath ? '/' . $cleanPath : '');
}

/**
 * Upload an image file securely to uploads/ directory.
 *
 * @param array $file $_FILES['image']
 * @return string|false Relative file path on success, false on failure
 */
function handle_image_upload(array $file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($origExt, $allowedExts, true)) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!$mime || !array_key_exists($mime, $allowedMimes)) {
        return false;
    }

    if (@getimagesize($file['tmp_name']) === false) {
        return false;
    }

    $ext = $allowedMimes[$mime];
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'item_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $filename;
    }

    return false;
}

/**
 * Check whether a stored relative image path exists inside uploads/.
 */
function item_image_exists(?string $relativePath): bool {
    if (empty($relativePath)) {
        return false;
    }
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    $targetFile = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
    return (bool)($uploadsDir && $targetFile && strpos($targetFile, $uploadsDir . DIRECTORY_SEPARATOR) === 0 && is_file($targetFile));
}

/**
 * Safely delete an uploaded image inside the uploads/ directory.
 */
function delete_uploaded_image(?string $relativePath): void {
    if (empty($relativePath)) {
        return;
    }
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    $targetFile = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
    if ($uploadsDir && $targetFile && strpos($targetFile, $uploadsDir . DIRECTORY_SEPARATOR) === 0 && is_file($targetFile)) {
        @unlink($targetFile);
    }
}
