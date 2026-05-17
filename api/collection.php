<?php
require __DIR__ . '/config.php';

$user_id = require_auth();

// ── GET: return all collected sticker IDs ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare('SELECT sticker_id FROM collections WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $ids = array_column($stmt->fetchAll(), 'sticker_id');
    json_out(['stickers' => $ids]);
}

// ── POST: toggle, add, remove, or batch ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = request_body();
    $action = $body['action'] ?? 'toggle';

    // ── Batch: add or remove an array of sticker IDs in one request ──────
    if ($action === 'batch_add' || $action === 'batch_remove') {
        $ids = $body['sticker_ids'] ?? [];
        if (!is_array($ids) || count($ids) > 1100) json_out(['error' => 'Invalid batch'], 400);

        // Validate every ID format
        foreach ($ids as $sid) {
            if (!preg_match('/^[A-Z0-9]+-\d+$/', $sid)) {
                json_out(['error' => "Invalid sticker id: $sid"], 400);
            }
        }

        db()->beginTransaction();
        try {
            if ($action === 'batch_remove') {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = db()->prepare(
                    "DELETE FROM collections WHERE user_id = ? AND sticker_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$user_id], $ids));
            } else {
                $stmt = db()->prepare(
                    'INSERT IGNORE INTO collections (user_id, sticker_id) VALUES (?, ?)'
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

    // ── Single sticker toggle / add / remove ─────────────────────────────
    $sticker_id = trim($body['sticker_id'] ?? '');
    if (!preg_match('/^[A-Z0-9]+-\d+$/', $sticker_id)) {
        json_out(['error' => 'Invalid sticker id'], 400);
    }

    if ($action === 'add') {
        db()->prepare('INSERT IGNORE INTO collections (user_id, sticker_id) VALUES (?, ?)')
            ->execute([$user_id, $sticker_id]);
        json_out(['collected' => true, 'sticker_id' => $sticker_id]);
    }

    if ($action === 'remove') {
        db()->prepare('DELETE FROM collections WHERE user_id = ? AND sticker_id = ?')
            ->execute([$user_id, $sticker_id]);
        json_out(['collected' => false, 'sticker_id' => $sticker_id]);
    }

    // Default: toggle
    $stmt = db()->prepare(
        'SELECT 1 FROM collections WHERE user_id = ? AND sticker_id = ?'
    );
    $stmt->execute([$user_id, $sticker_id]);

    if ($stmt->fetch()) {
        db()->prepare('DELETE FROM collections WHERE user_id = ? AND sticker_id = ?')
            ->execute([$user_id, $sticker_id]);
        json_out(['collected' => false, 'sticker_id' => $sticker_id]);
    } else {
        db()->prepare('INSERT INTO collections (user_id, sticker_id) VALUES (?, ?)')
            ->execute([$user_id, $sticker_id]);
        json_out(['collected' => true, 'sticker_id' => $sticker_id]);
    }
}

json_out(['error' => 'Method not allowed'], 405);
