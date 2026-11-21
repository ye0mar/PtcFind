<?php
/**
 * Lost Items Action Processor (CRUD Operations)
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash_messages.php';
require_once __DIR__ . '/../includes/notifications.php';

check_csrf();

$pdo = get_db_connection();
$action = $_POST['action'] ?? '';
$user = current_user();

switch ($action) {
    case 'create':
        $itemName     = trim($_POST['item_name'] ?? '');
        $categoryId   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $dateLost     = trim($_POST['date_lost'] ?? '');
        $locationLost = trim($_POST['location_lost'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $contactInfo  = trim($_POST['contact_info'] ?? '');

        if (empty($itemName) || empty($dateLost) || empty($locationLost) || empty($description) || empty($contactInfo)) {
            set_flash('error', 'Please fill in all required fields.');
            header('Location: ' . base_url('lost_items/report.php'));
            exit;
        }

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploaded = handle_image_upload($_FILES['image']);
            if (!$uploaded) {
                set_flash('error', 'Invalid image upload or file exceeds 5MB limit. Allowed: JPG, JPEG, PNG, WEBP.');
                header('Location: ' . base_url('lost_items/report.php'));
                exit;
            }
            $imagePath = $uploaded;
        }

        try {
            ensure_notifications_table($pdo);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO lost_items (item_name, category_id, description, date_lost, location_lost, contact_info, image_path, status, reported_by, created_at)
                 VALUES (:item_name, :category_id, :description, :date_lost, :location_lost, :contact_info, :image_path, 'open', :reported_by, NOW())"
            );
            $stmt->execute([
                ':item_name'     => $itemName,
                ':category_id'   => $categoryId,
                ':description'   => $description,
                ':date_lost'     => $dateLost,
                ':location_lost' => $locationLost,
                ':contact_info'  => $contactInfo,
                ':image_path'    => $imagePath,
                ':reported_by'   => $user['id'],
            ]);

            $newId = (int)$pdo->lastInsertId();
            create_role_item_notifications($pdo, (int)$user['id'], 'lost_item', $newId, $itemName);
            $pdo->commit();

            log_activity('CREATE_LOST_ITEM', 'lost_items', $newId, "Reported lost item: {$itemName}");
            set_flash('success', "Lost item '{$itemName}' recorded successfully.");
            header('Location: ' . base_url('lost_items/index.php'));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_uploaded_image($imagePath);
            set_flash('error', 'Database error: ' . $e->getMessage());
            header('Location: ' . base_url('lost_items/report.php'));
            exit;
        }
        break;

    case 'update':
        $id           = (int)($_POST['id'] ?? 0);
        $itemName     = trim($_POST['item_name'] ?? '');
        $categoryId   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $dateLost     = trim($_POST['date_lost'] ?? '');
        $locationLost = trim($_POST['location_lost'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $contactInfo  = trim($_POST['contact_info'] ?? '');
        $status       = in_array($_POST['status'] ?? '', ['open', 'matched', 'returned', 'closed'], true) ? $_POST['status'] : 'open';

        if (!$id || empty($itemName) || empty($dateLost) || empty($locationLost) || empty($description) || empty($contactInfo)) {
            set_flash('error', 'Invalid input data. Please fill in all required fields.');
            header('Location: ' . ($id ? base_url('lost_items/edit.php?id=' . $id) : base_url('lost_items/index.php')));
            exit;
        }

        try {
            $oldStmt = $pdo->prepare("SELECT image_path FROM lost_items WHERE id = :id LIMIT 1");
            $oldStmt->execute([':id' => $id]);
            $oldImage = $oldStmt->fetchColumn();

            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imagePath = handle_image_upload($_FILES['image']);
                if (!$imagePath) {
                    set_flash('error', 'Invalid image upload or file exceeds 5MB limit. Allowed: JPG, JPEG, PNG, WEBP.');
                    header('Location: ' . base_url('lost_items/edit.php?id=' . $id));
                    exit;
                }
            }

            if ($imagePath) {
                $stmt = $pdo->prepare(
                    "UPDATE lost_items
                     SET item_name = :name, category_id = :cat, date_lost = :dl, location_lost = :loc,
                         description = :desc, contact_info = :ci, status = :st, image_path = :img, updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':name' => $itemName,
                    ':cat'  => $categoryId,
                    ':dl'   => $dateLost,
                    ':loc'  => $locationLost,
                    ':desc' => $description,
                    ':ci'   => $contactInfo,
                    ':st'   => $status,
                    ':img'  => $imagePath,
                    ':id'   => $id,
                ]);
                if ($oldImage && $oldImage !== $imagePath) {
                    delete_uploaded_image($oldImage);
                }
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE lost_items
                     SET item_name = :name, category_id = :cat, date_lost = :dl, location_lost = :loc,
                         description = :desc, contact_info = :ci, status = :st, updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':name' => $itemName,
                    ':cat'  => $categoryId,
                    ':dl'   => $dateLost,
                    ':loc'  => $locationLost,
                    ':desc' => $description,
                    ':ci'   => $contactInfo,
                    ':st'   => $status,
                    ':id'   => $id,
                ]);
            }

            log_activity('UPDATE_LOST_ITEM', 'lost_items', $id, "Updated lost item #{$id} ({$itemName}) status: {$status}");
            set_flash('success', "Lost item updated successfully.");
            header('Location: ' . base_url('lost_items/view.php?id=' . $id));
            exit;
        } catch (Exception $e) {
            set_flash('error', 'Update failed: ' . $e->getMessage());
            header('Location: ' . base_url('lost_items/edit.php?id=' . $id));
            exit;
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $imgStmt = $pdo->prepare("SELECT image_path FROM lost_items WHERE id = :id LIMIT 1");
                $imgStmt->execute([':id' => $id]);
                $oldImage = $imgStmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM lost_items WHERE id = :id");
                $stmt->execute([':id' => $id]);
                delete_uploaded_image($oldImage ?: null);

                log_activity('DELETE_LOST_ITEM', 'lost_items', $id, "Deleted lost item record #{$id}");
                set_flash('success', "Lost item record deleted.");
            } catch (Exception $e) {
                set_flash('error', 'Deletion failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . base_url('lost_items/index.php'));
        exit;

    default:
        header('Location: ' . base_url('lost_items/index.php'));
        exit;
}
