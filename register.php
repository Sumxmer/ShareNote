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

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

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

            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id;
                log_security_event($conn, $newUserId, $username, 'REGISTER', 'สมัครสมาชิกใหม่สำเร็จ');
                $stmt->close();
                $conn->close();

                flash('success', 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ');
                header('Location: login.php');
                exit;
            } else {
                error_log('Register insert failed: ' . $stmt->error);
                $errors[] = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'; // ไม่แสดง SQL error จริง (ข้อ 7)
            }
        }
        $conn->close();
    }
}

$pageTitle = 'สมัครสมาชิก';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px; margin:0 auto;">
    <h2 class="page-title">สมัครสมาชิก</h2>
    <p class="subtitle">สร้างบัญชีเพื่อเริ่มแชร์และดาวน์โหลดชีทสรุป</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?>
                <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="username">ชื่อผู้ใช้ (username)</label>
        <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required maxlength="50">

        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="100">

        <label for="full_name">ชื่อ-นามสกุล</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required maxlength="100">

        <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร มีตัวเลขและตัวอักษร)</label>
        <input type="password" id="password" name="password" required minlength="8" maxlength="100">

        <label for="confirm_password">ยืนยันรหัสผ่าน</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" maxlength="100">

        <button type="submit" class="btn" style="width:100%;">สมัครสมาชิก</button>
    </form>
    <p style="text-align:center; margin-top:14px; font-size:0.9rem;">
        มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
