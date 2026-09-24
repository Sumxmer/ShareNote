<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';

if (!empty($_SESSION['user_id'])) {
    $conn = getDbConnection();
    log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'] ?? null, 'LOGOUT', 'ออกจากระบบ');
    $conn->close();
}

// เคลียร์ session ทั้งหมดอย่างปลอดภัย
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php');
exit;
