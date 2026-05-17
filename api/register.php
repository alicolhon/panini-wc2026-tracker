<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$body     = request_body();
$username = trim($body['username'] ?? '');
$email    = trim($body['email']    ?? '');
$password =      $body['password'] ?? '';

// Validation
if (strlen($username) < 3)                        json_out(['error' => 'Username must be at least 3 characters'], 400);
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username))  json_out(['error' => 'Username can only contain letters, numbers and underscores'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    json_out(['error' => 'Invalid email address'], 400);
if (strlen($password) < 6)                        json_out(['error' => 'Password must be at least 6 characters'], 400);

try {
    db()->prepare(
        'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)'
    )->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
    ]);

    json_out(['success' => true, 'message' => 'Account created']);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        json_out(['error' => 'Username or email is already taken'], 409);
    }
    json_out(['error' => 'Server error – please try again'], 500);
}
