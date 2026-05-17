<?php
// ── Database credentials ──────────────────────────────────────────────────
define('DB_HOST', 'sql113.infinityfree.com');
define('DB_NAME', 'if0_41943851_database1');
define('DB_USER', 'if0_41943851');
define('DB_PASS', 'YOUR_DB_PASSWORD'); // ← set this to your InfinityFree DB password

// ── Shared PDO connection (singleton) ────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

// ── Send JSON response and exit ───────────────────────────────────────────
function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit; // preflight
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Verify Bearer token, return user_id or 401 ───────────────────────────
function require_auth(): int {
    // Apache on shared hosting often strips Authorization; check several sources
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';
    if (!$header && function_exists('getallheaders')) {
        $all    = getallheaders();
        $header = $all['Authorization'] ?? $all['authorization'] ?? '';
    }
    $token = str_replace('Bearer ', '', $header);
    if (!$token) json_out(['error' => 'Not authenticated'], 401);

    $stmt = db()->prepare(
        'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) json_out(['error' => 'Session expired – please log in again'], 401);

    return (int) $row['user_id'];
}

// ── Parse JSON request body ───────────────────────────────────────────────
function request_body(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Handle OPTIONS preflight on every file
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    exit;
}
