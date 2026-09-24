<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

require_login(); // ข้อ 2

$conn = getDbConnection();
$noteId = (int)($_GET['id'] ?? $_POST['note_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM notes WHERE note_id = ?");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(404);
    die('ไม่พบชีทที่ต้องการแก้ไข');
}

// ข้อ 3: Authorization - User แก้ไขได้เฉพาะของตัวเอง, Admin แก้ไขได้ทั้งหมด
if ((int)$note['user_id'] !== (int)$_SESSION['user_id'] && !is_admin()) {
    http_response_code(403);
    die('คุณไม่มีสิทธิ์แก้ไขชีทนี้');
}

// ดึงชื่อรายวิชาปัจจุบันมาแสดงใน input
$stmt = $conn->prepare("SELECT subject_name FROM subjects WHERE subject_id = ?");
$stmt->bind_param('i', $note['subject_id']);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();
$currentSubjectName = $subject['subject_name'] ?? '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // ข้อ 9

    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $subjectName  = trim($_POST['subject_name'] ?? '');

    if ($e = validate_title($title)) $errors[] = $e;
    if (mb_strlen($description) > 2000) $errors[] = 'คำอธิบายยาวเกินไป';
    if ($subjectName === '') {
        $errors[] = 'กรุณากรอกรายวิชา';
    } elseif (mb_strlen($subjectName) > 150) {
        $errors[] = 'ชื่อรายวิชายาวเกินไป (สูงสุด 150 ตัวอักษร)';
    }

    $subjectId = 0;
    if (empty($errors)) {
        try {
            $subjectId = getOrCreateSubject($conn, $subjectName);
        } catch (Exception $e) {
            // ข้อ 7: ไม่แสดงรายละเอียดข้อผิดพลาดภายในระบบให้ผู้ใช้เห็น เก็บไว้ใน log แทน
            error_log('getOrCreateSubject failed: ' . $e->getMessage());
            $errors[] = 'ไม่สามารถบันทึกรายวิชาได้ กรุณาตรวจสอบชื่อรายวิชาแล้วลองใหม่อีกครั้ง';
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE notes SET title = ?, description = ?, subject_id = ? WHERE note_id = ?");
        $upd->bind_param('ssii', $title, $description, $subjectId, $noteId);
        $upd->execute();
        $upd->close();

        log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'NOTE_UPDATE', "แก้ไขชีท note_id={$noteId}");
        $conn->close();

        flash('success', 'บันทึกการแก้ไขเรียบร้อย');
        header('Location: dashboard.php');
        exit;
    }

    $note['title'] = $title;
    $note['description'] = $description;
    $currentSubjectName = $subjectName;
}

$pageTitle = 'แก้ไขชีท';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:560px; margin:0 auto;">
    <h2 class="page-title">แก้ไขชีท</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_note.php?id=<?= (int)$noteId ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="note_id" value="<?= (int)$noteId ?>">

        <label for="title">ชื่อชีท</label>
        <input type="text" id="title" name="title" value="<?= e($note['title']) ?>" required maxlength="200">

        <label for="subject_name">รายวิชา</label>
        <input type="text" id="subject_name" name="subject_name" value="<?= e($currentSubjectName) ?>" required maxlength="150" placeholder="เช่น Database and Web Security, Data Structures...">

        <label for="description">คำอธิบาย</label>
        <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($note['description']) ?></textarea>

        <p class="note-meta">หมายเหตุ: หากต้องการเปลี่ยนไฟล์ กรุณาลบชีทนี้แล้วอัปโหลดใหม่</p>

        <button type="submit" class="btn" style="width:100%;">บันทึกการแก้ไข</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
