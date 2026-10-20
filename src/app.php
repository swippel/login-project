<?php
declare(strict_types=1);

$appEnv = [];
foreach (file_exists(dirname(__DIR__) . '/.env') ? file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES) : [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
    $key = trim($key);
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;
    $value = trim($value);
    if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }
    $appEnv[$key] = $value;
}

function config(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? ($GLOBALS['appEnv'][$key] ?? $default) : $value;
}

function db(): PDO {
    static $pdo;
    if (isset($pdo)) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . config('DB_HOST', '127.0.0.1') . ';dbname=' . config('DB_NAME', 'auth_system') . ';charset=utf8mb4',
        config('DB_USER', 'root'), config('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('login_project');
    session_set_cookie_params([
        'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax', 'path' => '/',
    ]);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'");

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Ungültige Anfrage. Bitte lade die Seite neu.');
    }
}
function csrf_field(): void { echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function auth_state(int $id): ?string {
    $stmt = db()->prepare('SELECT u.password_hash, t.secret_ciphertext FROM users u LEFT JOIN user_totp t ON t.user_id = u.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? hash('sha256', $row['password_hash'] . "\0" . ($row['secret_ciphertext'] ?? '')) : null;
}
function user_id(): ?int {
    if (!isset($_SESSION['user_id'], $_SESSION['auth_state'])) return null;
    $state = auth_state((int)$_SESSION['user_id']);
    if ($state === null || !hash_equals($_SESSION['auth_state'], $state)) {
        unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['auth_state']);
        return null;
    }
    return (int)$_SESSION['user_id'];
}
function require_login(): int { return user_id() ?? redirect('/login.php'); }
function current_user(): ?array {
    if (!user_id()) return null;
    $stmt = db()->prepare('SELECT id, email, password_hash, last_login FROM users WHERE id = ?');
    $stmt->execute([user_id()]);
    return $stmt->fetch() ?: null;
}
function finish_login(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['pending_2fa'], $_SESSION['totp_setup'], $_SESSION['login_failures']);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['auth_state'] = auth_state((int)$user['id']);
    db()->prepare('UPDATE users SET last_login = UTC_TIMESTAMP() WHERE id = ?')->execute([$user['id']]);
}
function rate_allowed(string $scope, string $identifier, int $limit, int $seconds): bool {
    $bucket = hash('sha256', $scope . ':' . $identifier);
    $stmt = db()->prepare('INSERT INTO auth_rate_limits (bucket, attempts, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE attempts = IF(expires_at < UTC_TIMESTAMP(), 1, attempts + 1), expires_at = IF(expires_at < UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), expires_at)');
    $stmt->execute([$bucket, $seconds, $seconds]);
    $stmt = db()->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket = ?');
    $stmt->execute([$bucket]);
    return (int)$stmt->fetchColumn() <= $limit;
}
function hash_password(string $password): string { return 'v2:' . password_hash($password, PASSWORD_DEFAULT); }
function password_matches(string $password, string $stored): bool {
    if (str_starts_with($stored, 'v2:')) return password_verify($password, substr($stored, 3));
    // Existing accounts hashed the SHA-256 browser digest before bcrypt.
    return password_verify(hash('sha256', $password), $stored);
}
function page_start(string $title): void {
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . ' · Login</title><link rel="stylesheet" href="/assets/style.css"></head><body><main class="card"><header class="brand"><span class="brand-mark">✦</span><span>Login Projekt</span></header><h1>' . e($title) . '</h1>';
}
function page_end(): void { echo '</main></body></html>'; }
function message(string $text, string $kind = 'error'): void { echo '<p role="status" class="notice ' . e($kind) . '">' . e($text) . '</p>'; }
