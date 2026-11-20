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
 * Retrieve base application URL dynamically across root, subfolder, and proxy deployments.
 */
function base_url(string $path = ''): string {
    static $cachedRoot = null;

    if ($cachedRoot === null) {
        $root = '';
        $appFsRoot = realpath(__DIR__ . '/..');
        $docFsRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

        if ($appFsRoot && $docFsRoot) {
            $normApp = rtrim(str_replace('\\', '/', $appFsRoot), '/');
            $normDoc = rtrim(str_replace('\\', '/', $docFsRoot), '/');
            if (strcasecmp($normApp, $normDoc) === 0) {
                $root = '';
            } elseif (stripos($normApp, $normDoc . '/') === 0) {
                $root = substr($normApp, strlen($normDoc));
            } else {
                $docFsRoot = false; // Fall through to SCRIPT_NAME inspection
            }
        }

        if (!$docFsRoot) {
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/'));
            if ($scriptName === '' || $scriptName[0] !== '/') {
                $scriptName = '/' . ltrim($scriptName, '/');
            }
            $scriptDir = rtrim(dirname($scriptName), '/');
            if ($scriptDir === '.') {
                $scriptDir = '';
            }
            $subfolders = ['lost_items', 'found_items', 'matches', 'admin', 'profile', 'setup', 'notifications'];
            $pattern = '#/(' . implode('|', $subfolders) . ')$#';
            if (preg_match($pattern, $scriptDir)) {
                $scriptDir = (string)preg_replace($pattern, '', $scriptDir);
            }
            $root = $scriptDir;
        }

        $cachedRoot = ($root === '' || $root === '/') ? '' : '/' . trim($root, '/');
    }

    $cleanPath = ltrim($path, '/');
    return $cachedRoot . ($cleanPath ? '/' . $cleanPath : '');
}

/**
 * Resolve a deterministic relative URL with cache-busting version for CSS/JS assets.
 * Works regardless of web server URL rewriting, reverse proxies, or subfolder deployment.
 */
function asset_url(string $path): string {
    $cleanPath = ltrim($path, '/');
    $appFsRoot = realpath(__DIR__ . '/..');
    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $scriptDirFs = $scriptFile ? realpath(dirname($scriptFile)) : false;

    $prefix = '';
    if ($appFsRoot && $scriptDirFs) {
        $normApp = rtrim(str_replace('\\', '/', $appFsRoot), '/');
        $normScriptDir = rtrim(str_replace('\\', '/', $scriptDirFs), '/');
        if (strcasecmp($normScriptDir, $normApp) === 0) {
            $prefix = '';
        } elseif (stripos($normScriptDir, $normApp . '/') === 0) {
            $rel = trim(substr($normScriptDir, strlen($normApp)), '/');
            $depth = $rel === '' ? 0 : (substr_count($rel, '/') + 1);
            $prefix = str_repeat('../', $depth);
        } else {
            return base_url($cleanPath);
        }
    } else {
        $prefix = base_url('') !== '' ? rtrim(base_url(''), '/') . '/' : '/';
    }

    $fullFsPath = $appFsRoot ? $appFsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath) : '';
    $version = ($fullFsPath && is_file($fullFsPath)) ? ('?v=' . filemtime($fullFsPath)) : '';

    return $prefix . $cleanPath . $version;
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
