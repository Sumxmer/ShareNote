<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

// ถ้า login อยู่แล้ว ไม่ต้องให้สมัครซ้ำ
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = ['username' => '', 'email' => '', 'full_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // ข้อ 9: CSRF Protection

    $username = trim(input_text($_POST, 'username'));
    $email    = trim(input_text($_POST, 'email'));
    $fullName = trim(input_text($_POST, 'full_name'));
    $password = input_text($_POST, 'password');
    $confirm  = input_text($_POST, 'confirm_password');

    $old = ['username' => $username, 'email' => $email, 'full_name' => $fullName];

    // ข้อ 7: Input Validation (ฝั่ง Server)
    if ($e = validate_username($username)) $errors[] = $e;
    if ($e = validate_email($email)) $errors[] = $e;
    if ($e = validate_password($password)) $errors[] = $e;
    if ($fullName === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    if (mb_strlen($fullName) > 100) $errors[] = 'ชื่อ-นามสกุลยาวเกินไป';
    if ($password !== $confirm) $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';

    if (empty($errors)) {
        $conn = getDbConnection();

        // ตรวจสอบ username/email ซ้ำ ด้วย Prepared Statement (ข้อ 6)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้มีผู้ใช้งานแล้ว';
        }
        $stmt->close();

        if (empty($errors)) {
            // ข้อ 2: Password ต้องเก็บด้วย password_hash()
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'user')"
            );
            $stmt->bind_param('ssss', $username, $email, $hash, $fullName);

            $conn->begin_transaction();
            try {
                $stmt->execute();
                $newUserId = $stmt->insert_id;
                log_security_event($conn, $newUserId, $username, 'REGISTER', 'สมัครสมาชิกใหม่สำเร็จ');
                $conn->commit(); $stmt->close(); $conn->close();
                flash('success', 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ');
                header('Location: login.php'); exit;
            } catch (mysqli_sql_exception $error) {
                $conn->rollback();
                error_log('Register failed: ' . $error->getMessage());
                $errors[] = $error->getCode() === 1062 ? 'ชื่อผู้ใช้หรืออีเมลนี้มีผู้ใช้งานแล้ว' : 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่ภายหลัง';
                $stmt->close();
            }
        }
        $conn->close();
    }
}

$pageTitle = 'สมัครสมาชิก'; require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
<div class="auth-story"><div class="eyebrow">A little note. A big help.</div><h1>เริ่มต้นแบ่งปัน<br>ความรู้ดี ๆ</h1><p>เปลี่ยนสรุปที่คุณตั้งใจทำ ให้ช่วยเพื่อนอีกคน<br>สมัครสมาชิกเพื่อดาวน์โหลดชีทและส่งต่อความรู้</p><?php require __DIR__ . '/includes/paper_art.php'; ?></div>
<div class="auth-form"><h2 class="page-title">สร้างบัญชีของคุณ</h2><p class="subtitle">มาเป็นส่วนหนึ่งของพื้นที่การเรียนรู้ด้วยกัน</p>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="register.php">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label for="username">ชื่อผู้ใช้</label><input type="text" id="username" name="username" value="<?= e($old['username']) ?>" placeholder="เช่น studywithme" required minlength="4" maxlength="50" autocomplete="username"><p class="field-help">ตัวอักษรภาษาอังกฤษ ตัวเลข หรือ _ ความยาว 4–50 ตัวอักษร</p>
<label for="email">อีเมล</label><input type="email" id="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" required maxlength="100" autocomplete="email">
<label for="full_name">ชื่อที่ต้องการแสดง</label><input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" placeholder="ชื่อของคุณ" required maxlength="100" autocomplete="name">
<label for="password">รหัสผ่าน</label><div class="password-row"><input type="password" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร"><button type="button" class="password-toggle" data-password-toggle="password" aria-label="แสดงรหัสผ่าน" aria-pressed="false"><?= icon('eye') ?></button></div><p class="field-help">มีทั้งตัวอักษรภาษาอังกฤษและตัวเลข สูงสุด 72 ไบต์</p>
<label for="confirm_password">ยืนยันรหัสผ่าน</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="กรอกรหัสผ่านอีกครั้ง">
<button class="btn" type="submit">สมัครสมาชิก <?= icon('arrow') ?></button>
</form><p class="auth-switch">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
