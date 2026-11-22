<?php
/**
 * Staff Password Recovery Configuration
 * FoundU - Lost & Found Management System
 *
 * Administrators can configure the secret staff recovery code below
 * (or set the STAFF_RECOVERY_CODE environment variable).
 * NEVER expose this value on any public-facing page.
 */

if (
    basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'recovery_config.php'
    || basename($_SERVER['PHP_SELF'] ?? '') === 'recovery_config.php'
) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

if (!defined('STAFF_RECOVERY_CODE')) {
    define('STAFF_RECOVERY_CODE', getenv('STAFF_RECOVERY_CODE') ?: 'FOUNDU-STAFF-2026');
}
