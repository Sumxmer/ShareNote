<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_request(405, 'Method Not Allowed');
}

csrf_verify(); // ข้อ 9: CSRF Protection สำหรับฟังก์ชัน Delete

$conn = getDbConnection();
$noteId = input_id($_POST, 'note_id');

$stmt = $conn->prepare("SELECT user_id, file_name FROM notes WHERE note_id = ? AND status = 'active'");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    flash('error', 'ไม่พบชีทที่ต้องการลบ');
    header('Location: dashboard.php');
    exit;
}

// ข้อ 3: Authorization - User ลบได้เฉพาะของตัวเอง, Admin ลบได้ทั้งหมด
if ((int)$note['user_id'] !== (int)$_SESSION['user_id'] && !is_admin()) {
    abort_request(403, 'คุณไม่มีสิทธิ์ลบชีทนี้');
}

// ลบแบบ soft-delete (เก็บ record ไว้เพื่อดู log ย้อนหลังได้ แต่ผู้ใช้ทั่วไปจะมองไม่เห็นแล้ว)
$conn->begin_transaction();
$del = $conn->prepare("UPDATE notes SET status = 'removed' WHERE note_id = ?");
$del->bind_param('i', $noteId);
$del->execute();
$del->close();

log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'NOTE_DELETE', "ลบชีท note_id={$noteId}");
$conn->commit();
$conn->close();

flash('success', 'ลบชีทเรียบร้อยแล้ว');
header('Location: ' . (is_admin() ? 'admin/manage_notes.php' : 'dashboard.php'));
exit;
