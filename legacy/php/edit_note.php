<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

require_login(); // ข้อ 2

$conn = getDbConnection();
$noteId = isset($_GET['id']) ? input_id($_GET, 'id') : input_id($_POST, 'note_id');

$stmt = $conn->prepare("SELECT * FROM notes WHERE note_id = ? AND status = 'active'");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    abort_request(404, 'ไม่พบชีทที่ต้องการแก้ไข');
}

// ข้อ 3: Authorization - User แก้ไขได้เฉพาะของตัวเอง, Admin แก้ไขได้ทั้งหมด
if ((int)$note['user_id'] !== (int)$_SESSION['user_id'] && !is_admin()) {
    abort_request(403, 'คุณไม่มีสิทธิ์แก้ไขชีทนี้');
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

    $title        = trim(input_text($_POST, 'title'));
    $description  = trim(input_text($_POST, 'description'));
    $subjectName  = trim(input_text($_POST, 'subject_name'));

    if ($e = validate_title($title)) $errors[] = $e;
    if (mb_strlen($description) > 2000) $errors[] = 'คำอธิบายยาวเกินไป';
    if ($subjectName === '') {
        $errors[] = 'กรุณากรอกรายวิชา';
    } elseif (mb_strlen($subjectName) > 150) {
        $errors[] = 'ชื่อรายวิชายาวเกินไป (สูงสุด 150 ตัวอักษร)';
    }

    $subjectId = 0;
    if (empty($errors)) $conn->begin_transaction();
    if (empty($errors)) {
        try {
            $subjectId = getOrCreateSubject($conn, $subjectName);
        } catch (Exception $e) {
            // ข้อ 7: ไม่แสดงรายละเอียดข้อผิดพลาดภายในระบบให้ผู้ใช้เห็น เก็บไว้ใน log แทน
            $conn->rollback();
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
        $conn->commit();
        $conn->close();

        flash('success', 'บันทึกการแก้ไขเรียบร้อย');
        header('Location: dashboard.php');
        exit;
    }

    $note['title'] = $title;
    $note['description'] = $description;
    $currentSubjectName = $subjectName;
}

$pageTitle = 'แก้ไขชีท'; require __DIR__ . '/includes/header.php';
?>
<div class="breadcrumb"><a href="dashboard.php">ชีทของฉัน</a><span>/</span><span>แก้ไขชีท</span></div>
<div class="page-heading"><div><div class="eyebrow">Make your note better</div><h1 class="page-title">แก้ไขชีทสรุป</h1><p class="subtitle">เติมรายละเอียดให้ครบ เพื่อให้เพื่อน ๆ ค้นหาและอ่านได้ง่ายขึ้น</p></div></div>
<div class="form-layout"><section class="card form-card"><h2>รายละเอียดชีทสรุป</h2>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="edit_note.php?id=<?= (int)$noteId ?>">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="note_id" value="<?= (int)$noteId ?>">
<label for="title">ชื่อชีท <span class="note-meta">(จำเป็น)</span></label><input type="text" id="title" name="title" value="<?= e($note['title']) ?>" placeholder="เช่น สรุป SQL และการออกแบบฐานข้อมูล" required maxlength="200">
<label for="subject_name">รายวิชา <span class="note-meta">(จำเป็น)</span></label><input type="text" id="subject_name" name="subject_name" value="<?= e($currentSubjectName) ?>" placeholder="เช่น Database and Web Security" required maxlength="150">
<label for="description">คำอธิบายเพิ่มเติม <span class="note-meta">(ไม่บังคับ)</span></label><textarea id="description" name="description" rows="5" maxlength="2000" placeholder="ชีทนี้สรุปเรื่องอะไร เหมาะสำหรับใคร หรือมีคำแนะนำในการอ่านอย่างไร"><?= e($note['description']) ?></textarea><p class="field-help">เพิ่มรายละเอียดได้สูงสุด 2,000 ตัวอักษร</p>
<div class="help-card"><p class="mini-note"><?= icon('file') ?> ไฟล์ปัจจุบัน: <?= e($note['original_file_name']) ?></p><p>หากต้องการเปลี่ยนไฟล์ ให้นำชีทนี้ออกแล้วแบ่งปันชีทใหม่</p></div><div class="form-actions"><button class="btn" type="submit"><?= icon("file") ?> บันทึกการแก้ไข</button><a class="btn btn-secondary" href="dashboard.php">ยกเลิก</a></div>
</form></section><aside class="help-card"><h3>ชีทที่ดี เริ่มจากรายละเอียดที่ชัด</h3><div class="step-item"><span>1</span><p><strong>ตั้งชื่อให้อ่านแล้วเข้าใจ</strong>บอกหัวข้อสำคัญและเนื้อหาที่อยู่ในชีท</p></div><div class="step-item"><span>2</span><p><strong>ระบุรายวิชาให้ถูกต้อง</strong>ช่วยให้เพื่อนค้นหาและเลือกอ่านได้ง่าย</p></div><div class="step-item"><span>3</span><p><strong>แบ่งปันงานที่คุณมีสิทธิ์</strong>ตรวจความถูกต้องของเนื้อหาและข้อมูลส่วนตัวก่อนเผยแพร่</p></div><p class="mini-note"><?= icon('book') ?> ขอบคุณที่ช่วยสร้างพื้นที่การเรียนรู้</p></aside></div>
<?php $conn->close(); require __DIR__ . '/includes/footer.php'; ?>
