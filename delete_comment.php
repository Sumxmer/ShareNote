<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

csrf_verify(); // ข้อ 9

$conn = getDbConnection();
$commentId = (int)($_POST['comment_id'] ?? 0);
$noteId    = (int)($_POST['note_id'] ?? 0);

$stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
$stmt->bind_param('i', $commentId);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($comment && ((int)$comment['user_id'] === (int)$_SESSION['user_id'] || is_admin())) {
    $del = $conn->prepare("DELETE FROM comments WHERE comment_id = ?");
    $del->bind_param('i', $commentId);
    $del->execute();
    $del->close();
    log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'COMMENT_DELETE', "ลบคอมเมนต์ comment_id={$commentId}");
}

$conn->close();
header('Location: note_detail.php?id=' . $noteId . '#comments');
exit;
