<?php
/**
 * Flash Message Notifications
 * FoundU - Lost & Found Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set a flash message.
 *
 * @param string $type success|error|warning|info
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Check if flash messages exist.
 */
function has_flash(): bool {
    return !empty($_SESSION['flash_messages']);
}

/**
 * Retrieve and clear all flash messages.
 *
 * @return array
 */
function get_flash(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Render flash messages HTML alert blocks.
 */
function render_flash(): string {
    $messages = get_flash();
    if (empty($messages)) {
        return '';
    }

    $html = '<div class="flash-messages-container mb-4">';
    foreach ($messages as $msg) {
        $alertClass = 'alert-info';
        $iconClass = 'fa-circle-info';

        switch ($msg['type']) {
            case 'success':
                $alertClass = 'alert-success';
                $iconClass = 'fa-circle-check';
                break;
            case 'error':
            case 'danger':
                $alertClass = 'alert-danger';
                $iconClass = 'fa-triangle-exclamation';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                $iconClass = 'fa-circle-exclamation';
                break;
        }

        $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        $html .= '<i class="fa-solid ' . $iconClass . ' me-2 fs-5"></i>';
        $html .= '<div>' . htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}
