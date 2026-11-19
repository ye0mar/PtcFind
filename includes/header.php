<?php
/**
 * HTML Header & Head template
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash_messages.php';
require_once __DIR__ . '/notifications.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' - FoundU' : 'FoundU - Lost & Found Management System';
$systemName = get_setting('system_name', 'FoundU - Lost & Found Management System');
$headerUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?></title>
    <meta name="description" content="FoundU Campus Lost and Found Management System">
    <?php if ($headerUser): ?>
        <meta name="csrf-token" content="<?= sanitize(generate_csrf_token()) ?>">
    <?php endif; ?>

    <!-- Google Fonts: Fredoka & Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/forms.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/responsive.css') ?>">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $cssFile): ?>
            <link rel="stylesheet" href="<?= base_url($cssFile) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="bg-light"<?= $headerUser ? ' data-user-id="' . (int)$headerUser['id'] . '" data-notifications-api="' . sanitize(base_url('notifications/api.php')) . '"' : '' ?>>
<div class="app-wrapper d-flex flex-column min-vh-100">
