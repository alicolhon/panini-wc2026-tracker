<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token  = str_replace('Bearer ', '', $header);

if ($token) {
    db()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
}

json_out(['success' => true]);
