<?php
require __DIR__ . '/config.php';

$user_id = require_auth();

// ── GET: return sticker quantities map ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare('SELECT sticker_id, quantity FROM collections WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['sticker_id']] = (int)$row['quantity'];
    }
    json_out(['stickers' => $map]);
}

// ── POST: increment, decrement, or batch ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = request_body();
    $action = $body['action'] ?? 'increment';

    // ── Batch: add or remove an array of sticker IDs in one request ──────
    if ($action === 'batch_add' || $action === 'batch_remove') {
        $ids = $body['sticker_ids'] ?? [];
        if (!is_array($ids) || count($ids) > 1100) json_out(['error' => 'Invalid batch'], 400);

        foreach ($ids as $sid) {
            if (!preg_match('/^[A-Z0-9]+-\d+$/', $sid)) {
                json_out(['error' => "Invalid sticker id: $sid"], 400);
            }
        }

        db()->beginTransaction();
        try {
            if ($action === 'batch_remove') {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                db()->prepare(
                    "DELETE FROM collections WHERE user_id = ? AND sticker_id IN ($placeholders)"
                )->execute(array_merge([$user_id], $ids));
            } else {
                $stmt = db()->prepare(
                    'INSERT IGNORE INTO collections (user_id, sticker_id, quantity) VALUES (?, ?, 1)'
                );
                foreach ($ids as $sid) {
                    $stmt->execute([$user_id, $sid]);
                }
            }
            db()->commit();
            json_out(['success' => true, 'action' => $action, 'count' => count($ids)]);
        } catch (PDOException $e) {
            db()->rollBack();
            json_out(['error' => 'Batch operation failed'], 500);
        }
    }

    // ── Single sticker: increment or decrement ────────────────────────────
    $sticker_id = trim($body['sticker_id'] ?? '');
    if (!preg_match('/^[A-Z0-9]+-\d+$/', $sticker_id)) {
        json_out(['error' => 'Invalid sticker id'], 400);
    }

    if ($action === 'increment') {
        try {
            db()->prepare(
                'INSERT INTO collections (user_id, sticker_id, quantity) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE quantity = quantity + 1'
            )->execute([$user_id, $sticker_id]);
            $q = db()->prepare('SELECT quantity FROM collections WHERE user_id = ? AND sticker_id = ?');
            $q->execute([$user_id, $sticker_id]);
            json_out(['sticker_id' => $sticker_id, 'quantity' => (int)$q->fetchColumn()]);
        } catch (PDOException $e) {
            json_out(['error' => $e->getMessage()], 500);
        }
    }

    if ($action === 'decrement') {
        try {
            db()->prepare(
                'UPDATE collections SET quantity = quantity - 1
                 WHERE user_id = ? AND sticker_id = ? AND quantity > 0'
            )->execute([$user_id, $sticker_id]);
            db()->prepare(
                'DELETE FROM collections WHERE user_id = ? AND sticker_id = ? AND quantity = 0'
            )->execute([$user_id, $sticker_id]);
            json_out(['sticker_id' => $sticker_id, 'success' => true]);
        } catch (PDOException $e) {
            json_out(['error' => $e->getMessage()], 500);
        }
    }
}

json_out(['error' => 'Method not allowed'], 405);
