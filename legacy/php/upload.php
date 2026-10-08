<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';
require_login();
$conn = getDbConnection();
$errors = [];
$old = ['title' => '', 'description' => '', 'subject_name' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim(input_text($_POST, 'title'));
    $description = trim(input_text($_POST, 'description'));
    $subjectName = trim(input_text($_POST, 'subject_name'));
    $old = ['title' => $title, 'description' => $description, 'subject_name' => $subjectName];
    if ($error = validate_title($title)) $errors[] = $error;
    if (mb_strlen($description) > 2000) $errors[] = 'คำอธิบายยาวเกินไป (สูงสุด 2000 ตัวอักษร)';
    if ($subjectName === '' || mb_strlen($subjectName) > 150) $errors[] = 'กรุณากรอกรายวิชา ความยาวไม่เกิน 150 ตัวอักษร';
    $file = $_FILES['note_file'] ?? null;
    if (!is_array($file)) $errors[] = 'กรุณาเลือกไฟล์ชีทที่จะอัปโหลด';
    if (!$errors) {
        $uploadDir = __DIR__ . '/uploads';
        $uploadError = null;
        $fileInfo = validate_and_store_upload($file, $uploadDir, $uploadError);
        if (!$fileInfo) {
            $errors[] = $uploadError;
        } else {
            $conn->begin_transaction();
            try {
                $subjectId = getOrCreateSubject($conn, $subjectName);
                $stmt = $conn->prepare('INSERT INTO notes (user_id, subject_id, title, description, file_name, original_file_name, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iissssis', $_SESSION['user_id'], $subjectId, $title, $description, $fileInfo['file_name'], $fileInfo['original_file_name'], $fileInfo['file_size'], $fileInfo['file_type']);
                $stmt->execute();
                $noteId = $stmt->insert_id;
                $stmt->close();
                log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'NOTE_CREATE', "เพิ่มชีท note_id={$noteId}");
                log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'FILE_UPLOAD', 'อัปโหลดไฟล์: ' . $fileInfo['original_file_name']);
                $conn->commit();
                flash('success', 'แชร์ชีทเรียบร้อยแล้ว ขอบคุณที่แบ่งปันความรู้');
                header('Location: dashboard.php'); exit;
            } catch (Throwable $error) {
                $conn->rollback();
                @unlink($uploadDir . '/' . $fileInfo['file_name']);
                error_log('Upload failed: ' . $error->getMessage());
                $errors[] = 'ไม่สามารถบันทึกชีทได้ กรุณาลองใหม่ภายหลัง';
            }
        }
    }
}

$pageTitle = 'แบ่งปันชีท'; require __DIR__ . '/includes/header.php';
?>
<div class="breadcrumb"><a href="dashboard.php">ชีทของฉัน</a><span>/</span><span>แบ่งปันชีทใหม่</span></div>
<div class="page-heading"><div><div class="eyebrow">Share what you know</div><h1 class="page-title">แบ่งปันชีทสรุปของคุณ</h1><p class="subtitle">สรุปที่ดีของคุณ อาจทำให้วันอ่านหนังสือของใครง่ายขึ้น</p></div></div>
<div class="form-layout"><section class="card form-card"><h2>รายละเอียดชีทสรุป</h2>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="upload.php" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label for="title">ชื่อชีท <span class="note-meta">(จำเป็น)</span></label><input type="text" id="title" name="title" value="<?= e($old['title']) ?>" placeholder="เช่น สรุป SQL และการออกแบบฐานข้อมูล" required maxlength="200">
<label for="subject_name">รายวิชา <span class="note-meta">(จำเป็น)</span></label><input type="text" id="subject_name" name="subject_name" value="<?= e($old['subject_name']) ?>" placeholder="เช่น Database and Web Security" required maxlength="150">
<label for="description">คำอธิบายเพิ่มเติม <span class="note-meta">(ไม่บังคับ)</span></label><textarea id="description" name="description" rows="5" maxlength="2000" placeholder="ชีทนี้สรุปเรื่องอะไร เหมาะสำหรับใคร หรือมีคำแนะนำในการอ่านอย่างไร"><?= e($old['description']) ?></textarea><p class="field-help">เพิ่มรายละเอียดได้สูงสุด 2,000 ตัวอักษร</p>
<label for="note_file">ไฟล์ชีท <span class="note-meta">(จำเป็น)</span></label><label class="dropzone" for="note_file"><?= icon('upload') ?><strong>ลากไฟล์มาวาง หรือคลิกเพื่อเลือกไฟล์</strong><small>PDF, Word, PowerPoint, JPG, PNG · สูงสุด 10 MB</small><input type="file" id="note_file" name="note_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" required><span class="selected-file" data-file-label aria-live="polite"></span></label><div class="form-actions"><button class="btn" type="submit"><?= icon("upload") ?> เผยแพร่ชีทสรุป</button><a class="btn btn-secondary" href="dashboard.php">ยกเลิก</a></div>
</form></section><aside class="help-card"><h3>ชีทที่ดี เริ่มจากรายละเอียดที่ชัด</h3><div class="step-item"><span>1</span><p><strong>ตั้งชื่อให้อ่านแล้วเข้าใจ</strong>บอกหัวข้อสำคัญและเนื้อหาที่อยู่ในชีท</p></div><div class="step-item"><span>2</span><p><strong>ระบุรายวิชาให้ถูกต้อง</strong>ช่วยให้เพื่อนค้นหาและเลือกอ่านได้ง่าย</p></div><div class="step-item"><span>3</span><p><strong>แบ่งปันงานที่คุณมีสิทธิ์</strong>ตรวจความถูกต้องของเนื้อหาและข้อมูลส่วนตัวก่อนเผยแพร่</p></div><p class="mini-note"><?= icon('book') ?> ขอบคุณที่ช่วยสร้างพื้นที่การเรียนรู้</p></aside></div>
<?php $conn->close(); require __DIR__ . '/includes/footer.php'; ?>
