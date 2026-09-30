<?php
require_once __DIR__ . '/errors.php';
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}
define('SESSION_TIMEOUT_SECONDS', 30 * 60);
function login_url(): string {
    return str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../login.php' : 'login.php';
}
function destroy_user_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'],
            'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}
if (!empty($_SESSION['user_id'])) {
    header('Cache-Control: no-store');
    if (!isset($_SESSION['last_activity']) || time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS) {
        destroy_user_session();
        header('Location: ' . login_url() . '?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
