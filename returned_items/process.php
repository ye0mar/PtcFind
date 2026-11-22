<?php
/**
 * Returned Items Action Processor (Create & Delete)
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash_messages.php';
require_once __DIR__ . '/../includes/notifications.php';

check_csrf();

$pdo = get_db_connection();
ensure_returned_items_table($pdo);
ensure_notifications_table($pdo);

$action = $_POST['action'] ?? '';
$user = current_user();

switch ($action) {
    case 'create':
        $matchId         = !empty($_POST['match_id']) ? (int)$_POST['match_id'] : null;
        $lostItemId      = !empty($_POST['lost_item_id']) ? (int)$_POST['lost_item_id'] : null;
        $foundItemId     = !empty($_POST['found_item_id']) ? (int)$_POST['found_item_id'] : null;
        $itemName        = trim($_POST['item_name'] ?? '');
        $categoryId      = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $description     = trim($_POST['description'] ?? '');
        $receiverName    = trim($_POST['receiver_name'] ?? '');
        $receiverContact = trim($_POST['receiver_contact'] ?? '');
        $dateReturned    = trim($_POST['date_returned'] ?? '');
        $notes           = trim($_POST['notes'] ?? '');

        $backParams = [];
        if ($matchId) $backParams['match_id'] = $matchId;
        if ($lostItemId) $backParams['lost_item_id'] = $lostItemId;
        if ($foundItemId) $backParams['found_item_id'] = $foundItemId;
        $backUrl = base_url('returned_items/add.php' . (!empty($backParams) ? '?' . http_build_query($backParams) : ''));

        if ($itemName === '' || $receiverName === '' || $dateReturned === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReturned)) {
            set_flash('error', 'Please enter the item name, receiver name, and a valid return date.');
            header('Location: ' . $backUrl);
            exit;
        }

        if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] === UPLOAD_ERR_NO_FILE) {
            set_flash('error', 'Please upload a proof photo showing the return handover.');
            header('Location: ' . $backUrl);
            exit;
        }

        $proofImagePath = handle_image_upload($_FILES['proof_image']);
        if (!$proofImagePath) {
            set_flash('error', 'Invalid proof photo or file exceeds 5MB limit. Allowed: JPG, JPEG, PNG, WEBP.');
            header('Location: ' . $backUrl);
            exit;
        }

        $itemImagePath = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $itemImagePath = handle_image_upload($_FILES['item_image']);
            if (!$itemImagePath) {
                delete_uploaded_image($proofImagePath);
                set_flash('error', 'Invalid item photo or file exceeds 5MB limit. Allowed: JPG, JPEG, PNG, WEBP.');
                header('Location: ' . $backUrl);
                exit;
            }
        }

        try {
            if ($categoryId) {
                $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = :cid LIMIT 1");
                $catCheck->execute([':cid' => $categoryId]);
                if (!$catCheck->fetchColumn()) {
                    $categoryId = null;
                }
            }

            $pdo->beginTransaction();

            // Resolve match linkage if match_id provided
            if ($matchId) {
                $mStmt = $pdo->prepare("SELECT id, lost_item_id, found_item_id FROM matches WHERE id = :id LIMIT 1");
                $mStmt->execute([':id' => $matchId]);
                $mRow = $mStmt->fetch();
                if ($mRow) {
                    if (!$lostItemId) $lostItemId = (int)$mRow['lost_item_id'];
                    if (!$foundItemId) $foundItemId = (int)$mRow['found_item_id'];
                } else {
                    $matchId = null;
                }
            }

            // Prevent duplicate returns for the same Lost Item or Found Item
            $linkedLostImage = null;
            if ($lostItemId) {
                $lStmt = $pdo->prepare("SELECT id, status, image_path FROM lost_items WHERE id = :id LIMIT 1");
                $lStmt->execute([':id' => $lostItemId]);
                $lRow = $lStmt->fetch();
                if (!$lRow) {
                    $pdo->rollBack();
                    delete_uploaded_image($proofImagePath);
                    delete_uploaded_image($itemImagePath);
                    set_flash('error', 'Selected lost item record was not found.');
                    header('Location: ' . $backUrl);
                    exit;
                }
                $dupLostStmt = $pdo->prepare("SELECT id FROM returned_items WHERE lost_item_id = :lid LIMIT 1");
                $dupLostStmt->execute([':lid' => $lostItemId]);
                if ($dupLostStmt->fetchColumn() || $lRow['status'] === 'returned') {
                    $pdo->rollBack();
                    delete_uploaded_image($proofImagePath);
                    delete_uploaded_image($itemImagePath);
                    set_flash('error', 'This lost item has already been marked as returned.');
                    header('Location: ' . base_url('returned_items/index.php'));
                    exit;
                }
                $linkedLostImage = $lRow['image_path'] ?? null;
            }

            $linkedFoundImage = null;
            if ($foundItemId) {
                $fStmt = $pdo->prepare("SELECT id, status, image_path FROM found_items WHERE id = :id LIMIT 1");
                $fStmt->execute([':id' => $foundItemId]);
                $fRow = $fStmt->fetch();
                if (!$fRow) {
                    $pdo->rollBack();
                    delete_uploaded_image($proofImagePath);
                    delete_uploaded_image($itemImagePath);
                    set_flash('error', 'Selected found item record was not found.');
                    header('Location: ' . $backUrl);
                    exit;
                }
                $dupFoundStmt = $pdo->prepare("SELECT id FROM returned_items WHERE found_item_id = :fid LIMIT 1");
                $dupFoundStmt->execute([':fid' => $foundItemId]);
                if ($dupFoundStmt->fetchColumn() || $fRow['status'] === 'returned') {
                    $pdo->rollBack();
                    delete_uploaded_image($proofImagePath);
                    delete_uploaded_image($itemImagePath);
                    set_flash('error', 'This found item has already been recorded as returned.');
                    header('Location: ' . base_url('returned_items/index.php'));
                    exit;
                }
                $linkedFoundImage = $fRow['image_path'] ?? null;
            }

            if ($matchId && !$lostItemId && !$foundItemId) {
                $dupMatchStmt = $pdo->prepare("SELECT id FROM returned_items WHERE match_id = :mid LIMIT 1");
                $dupMatchStmt->execute([':mid' => $matchId]);
                if ($dupMatchStmt->fetchColumn()) {
                    $pdo->rollBack();
                    delete_uploaded_image($proofImagePath);
                    delete_uploaded_image($itemImagePath);
                    set_flash('error', 'This matched item has already been recorded as returned.');
                    header('Location: ' . base_url('returned_items/index.php'));
                    exit;
                }
            }

            $copiedFallbackImage = null;
            if (!$itemImagePath) {
                if (!empty($linkedFoundImage)) {
                    $copiedFallbackImage = copy_uploaded_image($linkedFoundImage, 'retitem_');
                }
                if (!$copiedFallbackImage && !empty($linkedLostImage)) {
                    $copiedFallbackImage = copy_uploaded_image($linkedLostImage, 'retitem_');
                }
                $itemImagePath = $copiedFallbackImage;
            }

            $insertStmt = $pdo->prepare(
                "INSERT INTO returned_items
                 (lost_item_id, found_item_id, match_id, item_name, category_id, description, item_image_path,
                  receiver_name, receiver_contact, date_returned, proof_image_path, notes, recorded_by, created_at)
                 VALUES
                 (:lid, :fid, :mid, :item_name, :cat_id, :desc, :item_img,
                  :rec_name, :rec_contact, :date_ret, :proof_img, :notes, :rec_by, NOW())"
            );
            $insertStmt->execute([
                ':lid'         => $lostItemId,
                ':fid'         => $foundItemId,
                ':mid'         => $matchId,
                ':item_name'   => $itemName,
                ':cat_id'      => $categoryId,
                ':desc'        => $description ?: null,
                ':item_img'    => $itemImagePath,
                ':rec_name'    => $receiverName,
                ':rec_contact' => $receiverContact ?: null,
                ':date_ret'    => $dateReturned,
                ':proof_img'   => $proofImagePath,
                ':notes'       => $notes ?: null,
                ':rec_by'      => (int)$user['id'],
            ]);

            $newId = (int)$pdo->lastInsertId();

            if ($lostItemId) {
                $pdo->prepare("UPDATE lost_items SET status = 'returned', updated_at = NOW() WHERE id = :id")
                    ->execute([':id' => $lostItemId]);
            }
            if ($foundItemId) {
                $pdo->prepare("UPDATE found_items SET status = 'returned', updated_at = NOW() WHERE id = :id")
                    ->execute([':id' => $foundItemId]);
            }

            create_role_item_notifications($pdo, (int)$user['id'], 'returned_item', $newId, $itemName);
            $pdo->commit();

            log_activity('CREATE_RETURNED_ITEM', 'returned_items', $newId, "Recorded return of '{$itemName}' to {$receiverName}");
            set_flash('success', "Return record for '{$itemName}' saved successfully.");
            header('Location: ' . base_url('returned_items/index.php'));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_uploaded_image($proofImagePath);
            delete_uploaded_image($itemImagePath);
            set_flash('error', 'Failed to save return record: ' . $e->getMessage());
            header('Location: ' . $backUrl);
            exit;
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->beginTransaction();

                $rowStmt = $pdo->prepare("SELECT * FROM returned_items WHERE id = :id LIMIT 1");
                $rowStmt->execute([':id' => $id]);
                $row = $rowStmt->fetch();

                if ($row) {
                    $pdo->prepare("DELETE FROM notifications WHERE related_item_type = 'returned_item' AND related_item_id = :id")
                        ->execute([':id' => $id]);
                    $pdo->prepare("DELETE FROM returned_items WHERE id = :id")
                        ->execute([':id' => $id]);

                    if (!empty($row['lost_item_id'])) {
                        $mLost = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE lost_item_id = :lid");
                        $mLost->execute([':lid' => (int)$row['lost_item_id']]);
                        $nextLostStatus = ((int)$mLost->fetchColumn() > 0) ? 'matched' : 'open';
                        $pdo->prepare("UPDATE lost_items SET status = :st, updated_at = NOW() WHERE id = :id AND status = 'returned'")
                            ->execute([':st' => $nextLostStatus, ':id' => (int)$row['lost_item_id']]);
                    }
                    if (!empty($row['found_item_id'])) {
                        $mFound = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE found_item_id = :fid");
                        $mFound->execute([':fid' => (int)$row['found_item_id']]);
                        $nextFoundStatus = ((int)$mFound->fetchColumn() > 0) ? 'matched' : 'available';
                        $pdo->prepare("UPDATE found_items SET status = :st, updated_at = NOW() WHERE id = :id AND status = 'returned'")
                            ->execute([':st' => $nextFoundStatus, ':id' => (int)$row['found_item_id']]);
                    }

                    $pdo->commit();
                    delete_uploaded_image($row['proof_image_path'] ?? null);
                    delete_uploaded_image($row['item_image_path'] ?? null);
                    log_activity('DELETE_RETURNED_ITEM', 'returned_items', $id, "Deleted returned item record #{$id}");
                    set_flash('success', 'Returned item record deleted.');
                } else {
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash('error', 'Deletion failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . base_url('returned_items/index.php'));
        exit;

    default:
        header('Location: ' . base_url('returned_items/index.php'));
        exit;
}
