<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') abort_request(405, 'กรุณาออกจากระบบผ่านปุ่มบนหน้าเว็บ');
csrf_verify();
if (!empty($_SESSION['user_id'])) {
    $conn = getDbConnection();
    log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'LOGOUT', 'ออกจากระบบ');
    $conn->close();
}
destroy_user_session();
header('Location: login.php'); exit;
