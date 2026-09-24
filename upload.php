<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

require_login(); // ข้อ 2: หน้าที่ต้อง Login ไม่สามารถเข้าถึงโดยตรงได้

$conn = getDbConnection();

$errors = [];
$old = ['title' => '', 'description' => '', 'subject_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // ข้อ 9: CSRF Protection

    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $subjectName  = trim($_POST['subject_name'] ?? '');

    $old = ['title' => $title, 'description' => $description, 'subject_name' => $subjectName];

    // ข้อ 7: Input Validation
    if ($e = validate_title($title)) $errors[] = $e;
    if (mb_strlen($description) > 2000) $errors[] = 'คำอธิบายยาวเกินไป (สูงสุด 2000 ตัวอักษร)';
    if ($subjectName === '') {
        $errors[] = 'กรุณากรอกรายวิชา';
    } elseif (mb_strlen($subjectName) > 150) {
        $errors[] = 'ชื่อรายวิชายาวเกินไป (สูงสุด 150 ตัวอักษร)';
    }

    // หาหรือสร้าง subject (INSERT IGNORE + SELECT)
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

    if (empty($_FILES['note_file']) || $_FILES['note_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'กรุณาเลือกไฟล์ชีทที่จะอัปโหลด';
    }

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/uploads';
        $uploadError = null;

        // หมายเหตุ: ตรวจชนิดไฟล์/ขนาด/เปลี่ยนชื่อไฟล์ก่อนจัดเก็บ
        $fileInfo = validate_and_store_upload($_FILES['note_file'], $uploadDir, $uploadError);

        if ($fileInfo === null) {
            $errors[] = $uploadError;
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO notes (user_id, subject_id, title, description, file_name, original_file_name, file_size, file_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iissssis',
                $_SESSION['user_id'],
                $subjectId,
                $title,
                $description,
                $fileInfo['file_name'],
                $fileInfo['original_file_name'],
                $fileInfo['file_size'],
                $fileInfo['file_type']
            );

            if ($stmt->execute()) {
                $noteId = $stmt->insert_id;
                // ข้อ 10: Security Logging - เพิ่มข้อมูล + Upload File
                log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'NOTE_CREATE', "เพิ่มชีท note_id={$noteId}");
                log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'FILE_UPLOAD', "อัปโหลดไฟล์: " . $fileInfo['original_file_name']);
                $stmt->close();
                $conn->close();

                flash('success', 'อัปโหลดชีทสำเร็จ');
                header('Location: dashboard.php');
                exit;
            } else {
                error_log('Upload insert failed: ' . $stmt->error);
                @unlink($uploadDir . '/' . $fileInfo['file_name']); // ลบไฟล์ทิ้งถ้า insert DB ไม่สำเร็จ
                $errors[] = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}

$pageTitle = 'อัปโหลดชีท';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:560px; margin:0 auto;">
    <h2 class="page-title">อัปโหลดชีทสรุป</h2>
    <p class="subtitle">รองรับไฟล์ PDF, Word, PowerPoint, JPG, PNG ขนาดไม่เกิน 10MB</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="upload.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="title">ชื่อชีท</label>
        <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" required maxlength="200">

        <label for="subject_name">รายวิชา</label>
        <input type="text" id="subject_name" name="subject_name" value="<?= e($old['subject_name']) ?>" required maxlength="150" placeholder="เช่น Database and Web Security, Data Structures...">

        <label for="description">คำอธิบาย (ไม่บังคับ)</label>
        <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($old['description']) ?></textarea>

        <label for="note_file">ไฟล์ชีท</label>
        <input type="file" id="note_file" name="note_file" required
               accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" style="margin-bottom:16px;">

        <button type="submit" class="btn" style="width:100%;">อัปโหลด</button>
    </form>
</div>

<?php
$conn->close();
require_once __DIR__ . '/includes/footer.php';
?>
