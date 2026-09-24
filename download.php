<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';

require_login(); // ข้อ 2: ต้อง Login ก่อนดาวน์โหลด

$conn = getDbConnection();

$noteId = (int)($_GET['id'] ?? 0);
if ($noteId <= 0) {
    http_response_code(400);
    die('คำขอไม่ถูกต้อง');
}

$stmt = $conn->prepare(
    "SELECT note_id, file_name, original_file_name, file_type FROM notes WHERE note_id = ? AND status = 'active'"
);
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(404);
    die('ไม่พบไฟล์ที่ต้องการ');
}

// ใช้ชื่อไฟล์จาก DB เท่านั้น (ไม่รับ path จาก user โดยตรง) ป้องกัน Path Traversal
$filePath = __DIR__ . '/uploads/' . basename($note['file_name']);

if (!file_exists($filePath)) {
    http_response_code(404);
    die('ไม่พบไฟล์บนเซิร์ฟเวอร์');
}

// อัปเดตตัวนับดาวน์โหลด + บันทึก log
$upd = $conn->prepare("UPDATE notes SET download_count = download_count + 1 WHERE note_id = ?");
$upd->bind_param('i', $noteId);
$upd->execute();
$upd->close();

$ins = $conn->prepare("INSERT INTO download_logs (note_id, user_id, ip_address) VALUES (?, ?, ?)");
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ins->bind_param('iis', $noteId, $_SESSION['user_id'], $ip);
$ins->execute();
$ins->close();

log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'FILE_DOWNLOAD', "ดาวน์โหลด note_id={$noteId}");
$conn->close();

// ส่งไฟล์กลับไปยังผู้ใช้
$mimeTypes = [
    'pdf' => 'application/pdf', 'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
];
$mime = $mimeTypes[$note['file_type']] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($note['original_file_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
