<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
$errors = []; $successMsg = flash('success'); $username = '';
if (!empty($_GET['timeout'])) $errors[] = 'เซสชันหมดอายุเนื่องจากไม่มีการใช้งาน กรุณาเข้าสู่ระบบใหม่';
if (!empty($_GET['revoked'])) $errors[] = 'บัญชีถูกระงับหรือไม่มีอยู่แล้ว กรุณาติดต่อผู้ดูแลระบบ';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim(input_text($_POST, 'username'));
    $password = input_text($_POST, 'password');
    if ($username === '' || $password === '' || mb_strlen($username) > 50 || strlen($password) > 72) {
        $errors[] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ถูกต้อง';
    } else {
        $conn = getDbConnection(); $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (is_login_locked_out($conn, $username, $clientIp)) {
            http_response_code(429);
            $errors[] = 'พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองอีกครั้ง';
            log_security_event($conn, null, $username, 'ACCOUNT_LOCKOUT', 'บล็อกการเข้าสู่ระบบชั่วคราว');
        } else {
            $stmt = $conn->prepare('SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ?');
            $stmt->bind_param('s', $username); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($user && password_verify($password, $user['password_hash']) && $user['status'] === 'active') {
                $conn->begin_transaction();
                log_security_event($conn, $user['user_id'], $username, 'LOGIN_SUCCESS', 'เข้าสู่ระบบสำเร็จ');
                $conn->commit(); $conn->close();
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                foreach (['user_id', 'username', 'full_name', 'role'] as $key) $_SESSION[$key] = $user[$key];
                $_SESSION['last_activity'] = time();
                header('Location: dashboard.php'); exit;
            }
            $errors[] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีไม่พร้อมใช้งาน';
            log_security_event($conn, null, $username, 'LOGIN_FAILED', 'เข้าสู่ระบบไม่สำเร็จ');
        }
        $conn->close();
    }
}
$pageTitle = 'เข้าสู่ระบบ'; require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
<div class="auth-story"><div class="eyebrow">Welcome back</div><h1>กลับมาเรียนรู้<br>ไปด้วยกัน</h1><p>ชีทสรุปดี ๆ และความรู้จากเพื่อน ๆ รอคุณอยู่<br>เข้าสู่ระบบเพื่อดาวน์โหลดและแบ่งปันชีทของคุณ</p><?php require __DIR__ . '/includes/paper_art.php'; ?><div class="auth-facts"><span><?= icon('book') ?> ความรู้จากเพื่อน</span><span><?= icon('upload') ?> แบ่งปันได้ทุกวัน</span></div></div>
<div class="auth-form"><h2 class="page-title">เข้าสู่ระบบ</h2><p class="subtitle">ยินดีต้อนรับกลับสู่ NoteShare</p>
<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="login.php">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label for="username">ชื่อผู้ใช้</label><input type="text" id="username" name="username" value="<?= e($username) ?>" placeholder="ชื่อผู้ใช้ของคุณ" required maxlength="50" autocomplete="username">
<label for="password">รหัสผ่าน</label><div class="password-row"><input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required maxlength="72" autocomplete="current-password"><button type="button" class="password-toggle" data-password-toggle="password" aria-label="แสดงรหัสผ่าน" aria-pressed="false"><?= icon('eye') ?></button></div>
<button class="btn" type="submit">เข้าสู่ระบบ <?= icon('arrow') ?></button>
</form><p class="auth-switch">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกฟรี</a></p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
