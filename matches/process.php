<?php
/**
 * Item Match Processor (Link, Return, Unlink)
 * FoundU - Lost & Found Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash_messages.php';

check_csrf();

$pdo = get_db_connection();
$action = $_POST['action'] ?? '';
$user = current_user();

switch ($action) {
    case 'create':
        $lostItemId  = (int)($_POST['lost_item_id'] ?? 0);
        $foundItemId = (int)($_POST['found_item_id'] ?? 0);
        $notes       = trim($_POST['notes'] ?? '');

        if (!$lostItemId || !$foundItemId) {
            set_flash('error', 'Please select both a lost item and a found item.');
            header('Location: ' . base_url('matches/index.php'));
            exit;
        }

        try {
            $pdo->beginTransaction();

            $lostCheck = $pdo->prepare("SELECT status FROM lost_items WHERE id = :id LIMIT 1");
            $lostCheck->execute([':id' => $lostItemId]);
            $lostStatus = $lostCheck->fetchColumn();

            $foundCheck = $pdo->prepare("SELECT status FROM found_items WHERE id = :id LIMIT 1");
            $foundCheck->execute([':id' => $foundItemId]);
            $foundStatus = $foundCheck->fetchColumn();

            if ($lostStatus !== 'open' || $foundStatus !== 'available') {
                $pdo->rollBack();
                set_flash('error', 'Selected items must be open (lost) and available (found) to create a match.');
                header('Location: ' . base_url('matches/index.php'));
                exit;
            }

            // Insert match record
            $stmt = $pdo->prepare(
                "INSERT INTO matches (lost_item_id, found_item_id, confirmed_by, notes, created_at)
                 VALUES (:lid, :fid, :uid, :notes, NOW())"
            );
            $stmt->execute([
                ':lid'   => $lostItemId,
                ':fid'   => $foundItemId,
                ':uid'   => $user['id'],
                ':notes' => $notes,
            ]);
            $matchId = $pdo->lastInsertId();

            // Update lost item status to 'matched'
            $pdo->prepare("UPDATE lost_items SET status = 'matched', updated_at = NOW() WHERE id = :id")->execute([':id' => $lostItemId]);

            // Update found item status to 'matched'
            $pdo->prepare("UPDATE found_items SET status = 'matched', updated_at = NOW() WHERE id = :id")->execute([':id' => $foundItemId]);

            $pdo->commit();

            log_activity('CREATE_MATCH', 'matches', (int)$matchId, "Created match #{$matchId} (Lost #{$lostItemId} <-> Found #{$foundItemId})");
            set_flash('success', 'Match created successfully. Both items updated to Matched status.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Failed to create match: ' . $e->getMessage());
        }

        header('Location: ' . base_url('matches/index.php'));
        exit;

    case 'mark_returned':
        $matchId = (int)($_POST['match_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            $matchStmt = $pdo->prepare("SELECT lost_item_id, found_item_id FROM matches WHERE id = :id LIMIT 1");
            $matchStmt->execute([':id' => $matchId]);
            $matchRow = $matchStmt->fetch();

            if (!$matchRow) {
                $pdo->rollBack();
                set_flash('error', 'Match record not found.');
                header('Location: ' . base_url('matches/index.php'));
                exit;
            }

            $pdo->prepare("UPDATE lost_items SET status = 'returned', updated_at = NOW() WHERE id = :id")->execute([':id' => $matchRow['lost_item_id']]);
            $pdo->prepare("UPDATE found_items SET status = 'returned', updated_at = NOW() WHERE id = :id")->execute([':id' => $matchRow['found_item_id']]);

            $pdo->commit();

            log_activity('RETURN_ITEM', 'matches', $matchId, "Marked match #{$matchId} as claimed & returned to owner");
            set_flash('success', 'Item marked as successfully returned to owner.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Error updating return status: ' . $e->getMessage());
        }

        header('Location: ' . base_url('matches/index.php'));
        exit;

    case 'delete':
        $matchId = (int)($_POST['match_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Find linked items
            $stmt = $pdo->prepare("SELECT lost_item_id, found_item_id FROM matches WHERE id = :id");
            $stmt->execute([':id' => $matchId]);
            $match = $stmt->fetch();

            if ($match) {
                // Delete match
                $pdo->prepare("DELETE FROM matches WHERE id = :id")->execute([':id' => $matchId]);

                // Reset items to open / available if they were matched
                $pdo->prepare("UPDATE lost_items SET status = 'open', updated_at = NOW() WHERE id = :id AND status = 'matched'")->execute([':id' => $match['lost_item_id']]);
                $pdo->prepare("UPDATE found_items SET status = 'available', updated_at = NOW() WHERE id = :id AND status = 'matched'")->execute([':id' => $match['found_item_id']]);
            }

            $pdo->commit();

            log_activity('DELETE_MATCH', 'matches', $matchId, "Unlinked match #{$matchId}");
            set_flash('success', 'Match unlinked. Items restored to open/available status.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Failed to unlink match: ' . $e->getMessage());
        }

        header('Location: ' . base_url('matches/index.php'));
        exit;

    default:
        header('Location: ' . base_url('matches/index.php'));
        exit;
}
