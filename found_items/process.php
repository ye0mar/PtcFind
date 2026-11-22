<?php
/**
 * Found Items Action Processor (CRUD Operations)
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
        $itemName        = trim($_POST['item_name'] ?? '');
        $categoryId      = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $dateFound       = trim($_POST['date_found'] ?? '');
        $locationFound   = trim($_POST['location_found'] ?? '');
        $storageLocation = trim($_POST['storage_location'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $notes           = trim($_POST['notes'] ?? '');

        if (empty($itemName) || empty($dateFound) || empty($locationFound) || empty($description) || empty($storageLocation)) {
            set_flash('error', 'Please fill in all required fields.');
            header('Location: ' . base_url('found_items/add.php'));
            exit;
        }

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = handle_image_upload($_FILES['image']);
            if (!$imagePath) {
                set_flash('error', 'Invalid image upload or file exceeds 5MB limit. Allowed: JPG, JPEG, PNG, WEBP.');
                header('Location: ' . base_url('found_items/add.php'));
                exit;
            }
        }

        try {
            ensure_notifications_table($pdo);
            if ($categoryId) {
                $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = :cid LIMIT 1");
                $catCheck->execute([':cid' => $categoryId]);
                if (!$catCheck->fetchColumn()) {
                    $categoryId = null;
                }
            }
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO found_items (item_name, category_id, description, date_found, location_found, storage_location, notes, image_path, status, recorded_by, created_at)
                 VALUES (:item_name, :category_id, :description, :date_found, :location_found, :storage_location, :notes, :image_path, 'available', :recorded_by, NOW())"
            );
            $stmt->execute([
                ':item_name'        => $itemName,
                ':category_id'      => $categoryId,
                ':description'      => $description,
                ':date_found'       => $dateFound,
                ':location_found'   => $locationFound,
                ':storage_location' => $storageLocation,
                ':notes'            => $notes,
                ':image_path'       => $imagePath,
                ':recorded_by'      => $user['id'],
            ]);

            $newId = (int)$pdo->lastInsertId();
            create_role_item_notifications($pdo, (int)$user['id'], 'found_item', $newId, $itemName);
            $pdo->commit();

            log_activity('CREATE_FOUND_ITEM', 'found_items', $newId, "Recorded found item: {$itemName} (Storage: {$storageLocation})");
            set_flash('success', "Found item '{$itemName}' recorded into storage.");
            header('Location: ' . base_url('found_items/index.php'));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_uploaded_image($imagePath);
            set_flash('error', 'Database error: ' . $e->getMessage());
            header('Location: ' . base_url('found_items/add.php'));
            exit;
        }
        break;

    case 'update':
        $id              = (int)($_POST['id'] ?? 0);
        $itemName        = trim($_POST['item_name'] ?? '');
        $categoryId      = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $dateFound       = trim($_POST['date_found'] ?? '');
        $locationFound   = trim($_POST['location_found'] ?? '');
        $storageLocation = trim($_POST['storage_location'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $notes           = trim($_POST['notes'] ?? '');
        $status          = in_array($_POST['status'] ?? '', ['available', 'matched', 'returned', 'closed'], true) ? $_POST['status'] : 'available';

        if (!$id || empty($itemName) || empty($dateFound) || empty($locationFound) || empty($storageLocation) || empty($description)) {
            set_flash('error', 'Invalid input data. Please fill in all required fields.');
            header('Location: ' . ($id ? base_url('found_items/edit.php?id=' . $id) : base_url('found_items/index.php')));
            exit;
        }

        try {
            if ($categoryId) {
                $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = :cid LIMIT 1");
                $catCheck->execute([':cid' => $categoryId]);
                if (!$catCheck->fetchColumn()) {
                    $categoryId = null;
                }
            }
            $oldStmt = $pdo->prepare("SELECT image_path FROM found_items WHERE id = :id LIMIT 1");
            $oldStmt->execute([':id' => $id]);
            $oldImage = $oldStmt->fetchColumn();

            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imagePath = handle_image_upload($_FILES['image']);
                if (!$imagePath) {
                    set_flash('error', 'Invalid image upload or file exceeds 5MB limit. Allowed: JPG, JPEG, PNG, WEBP.');
                    header('Location: ' . base_url('found_items/edit.php?id=' . $id));
                    exit;
                }
            }

            if ($imagePath) {
                $stmt = $pdo->prepare(
                    "UPDATE found_items
                     SET item_name = :name, category_id = :cat, date_found = :df, location_found = :loc,
                         storage_location = :stor, description = :desc, notes = :notes, status = :st, image_path = :img, updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':name'  => $itemName,
                    ':cat'   => $categoryId,
                    ':df'    => $dateFound,
                    ':loc'   => $locationFound,
                    ':stor'  => $storageLocation,
                    ':desc'  => $description,
                    ':notes' => $notes,
                    ':st'    => $status,
                    ':img'   => $imagePath,
                    ':id'    => $id,
                ]);
                if ($oldImage && $oldImage !== $imagePath) {
                    delete_uploaded_image($oldImage);
                }
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE found_items
                     SET item_name = :name, category_id = :cat, date_found = :df, location_found = :loc,
                         storage_location = :stor, description = :desc, notes = :notes, status = :st, updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':name'  => $itemName,
                    ':cat'   => $categoryId,
                    ':df'    => $dateFound,
                    ':loc'   => $locationFound,
                    ':stor'  => $storageLocation,
                    ':desc'  => $description,
                    ':notes' => $notes,
                    ':st'    => $status,
                    ':id'    => $id,
                ]);
            }

            log_activity('UPDATE_FOUND_ITEM', 'found_items', $id, "Updated found item #{$id} ({$itemName}) status: {$status}");
            set_flash('success', "Found item record updated.");
            header('Location: ' . base_url('found_items/view.php?id=' . $id));
            exit;
        } catch (Exception $e) {
            set_flash('error', 'Update failed: ' . $e->getMessage());
            header('Location: ' . base_url('found_items/edit.php?id=' . $id));
            exit;
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                ensure_notifications_table($pdo);
                $imgStmt = $pdo->prepare("SELECT image_path FROM found_items WHERE id = :id LIMIT 1");
                $imgStmt->execute([':id' => $id]);
                $oldImage = $imgStmt->fetchColumn();

                $pdo->prepare("DELETE FROM notifications WHERE related_item_type = 'found_item' AND related_item_id = :id")->execute([':id' => $id]);
                $stmt = $pdo->prepare("DELETE FROM found_items WHERE id = :id");
                $stmt->execute([':id' => $id]);
                delete_uploaded_image($oldImage ?: null);

                log_activity('DELETE_FOUND_ITEM', 'found_items', $id, "Deleted found item record #{$id}");
                set_flash('success', "Found item record deleted.");
            } catch (Exception $e) {
                set_flash('error', 'Deletion failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . base_url('found_items/index.php'));
        exit;

    default:
        header('Location: ' . base_url('found_items/index.php'));
        exit;
}
