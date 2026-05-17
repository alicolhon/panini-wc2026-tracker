<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$body     = request_body();
$username = trim($body['username'] ?? '');
$password =      $body['password'] ?? '';

if (!$username || !$password) json_out(['error' => 'Please fill all fields'], 400);

// Look up user
$stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_out(['error' => 'Wrong username or password'], 401);
}

// Generate a secure random token
$token = bin2hex(random_bytes(32)); // 64-char hex string

// Store session (30-day expiry)
db()->prepare(
    'INSERT INTO sessions (token, user_id, expires_at)
     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
)->execute([$token, $user['id']]);

// Update last login timestamp
db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
    ->execute([$user['id']]);

json_out([
    'token'    => $token,
    'username' => $username,
]);
