<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$successMsg = flash('success');
if (!empty($_GET['timeout'])) {
    $errors[] = 'เซสชันหมดอายุเนื่องจากไม่มีการใช้งาน กรุณาเข้าสู่ระบบใหม่';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // ข้อ 9: CSRF Protection

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $conn = getDbConnection();
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // ป้องกัน Brute-force: ถ้า login ผิดเกิน 5 ครั้งใน 15 นาทีล่าสุด (นับตาม username หรือ IP)
        // ให้ปฏิเสธทันทีโดยไม่ต้องไปตรวจรหัสผ่านต่อ และไม่บอกเวลาที่เหลือ (กันการ enumeration)
        if (is_login_locked_out($conn, $username, $clientIp)) {
            $errors[] = 'พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่ภายหลัง';
            log_security_event($conn, null, $username, 'ACCOUNT_LOCKOUT', 'บล็อกการ login ชั่วคราวเนื่องจากพยายามผิดเกินกำหนด');
            $conn->close();
            $pageTitle = 'เข้าสู่ระบบ';
            require_once __DIR__ . '/includes/header.php';
            ?>
            <div class="card" style="max-width:420px; margin:0 auto;">
                <h2 class="page-title">เข้าสู่ระบบ</h2>
                <div class="alert alert-error">
                    <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
                </div>
                <p style="text-align:center; margin-top:14px; font-size:0.9rem;">
                    <a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
                </p>
            </div>
            <?php
            require_once __DIR__ . '/includes/footer.php';
            exit;
        }

        // ข้อ 6: ป้องกัน SQL Injection ด้วย Prepared Statement
        $stmt = $conn->prepare(
            "SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ?"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // ข้อ 2: ตรวจสอบด้วย password_verify()
        if ($user && password_verify($password, $user['password_hash'])) {

            if ($user['status'] === 'suspended') {
                $errors[] = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
                log_security_event($conn, $user['user_id'], $username, 'LOGIN_FAILED', 'บัญชีถูกระงับ');
            } else {
                // ข้อ 4: session_regenerate_id() หลัง Login สำเร็จ เพื่อป้องกัน Session Fixation
                session_regenerate_id(true);

                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['full_name']  = $user['full_name'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['last_activity'] = time();

                log_security_event($conn, $user['user_id'], $username, 'LOGIN_SUCCESS', 'เข้าสู่ระบบสำเร็จ');
                $conn->close();

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $errors[] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            // ข้อ 10: บันทึก Login ไม่สำเร็จ (ไม่ระบุว่าผิดที่ username หรือ password เพื่อความปลอดภัย)
            log_security_event($conn, null, $username, 'LOGIN_FAILED', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }
        $conn->close();
    }
}

$pageTitle = 'เข้าสู่ระบบ';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:420px; margin:0 auto;">
    <h2 class="page-title">เข้าสู่ระบบ</h2>
    <p class="subtitle">ยินดีต้อนรับกลับมา</p>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= e($successMsg) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?>
                <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="username">ชื่อผู้ใช้</label>
        <input type="text" id="username" name="username" required maxlength="50" autofocus>

        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" required maxlength="100">

        <button type="submit" class="btn" style="width:100%;">เข้าสู่ระบบ</button>
    </form>
    <p style="text-align:center; margin-top:14px; font-size:0.9rem;">
        ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
