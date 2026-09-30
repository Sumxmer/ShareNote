<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/csrf.php';

require_admin();

$conn = getDbConnection();
$errors = [];

// ------------------------------------------------------------------
// จัดการคำขอ POST: เปลี่ยนสถานะ (ระงับ/ปลดระงับ) หรือเปลี่ยน role
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // ข้อ 9: CSRF Protection

    $targetUserId = input_id($_POST, 'user_id');
    $action = input_text($_POST, 'action');
    if ($targetUserId < 1 || !in_array($action, ['toggle_status', 'toggle_role'], true)) abort_request(400, 'คำขอไม่ถูกต้อง');
    $conn->begin_transaction();

    // ป้องกันแอดมินระงับ/ลดสิทธิ์ตัวเอง โดยไม่ตั้งใจจนล็อกตัวเองออก
    if ($targetUserId === (int)$_SESSION['user_id']) {
        $errors[] = 'ไม่สามารถเปลี่ยนสถานะ/สิทธิ์ของบัญชีตัวเองได้';
    } elseif ($action === 'toggle_status') {
        $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u) abort_request(404, 'ไม่พบบัญชีผู้ใช้นี้');
        if ($u) {
            $newStatus = $u['status'] === 'active' ? 'suspended' : 'active';
            $upd = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $upd->bind_param('si', $newStatus, $targetUserId);
            $upd->execute();
            $upd->close();
            log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'ADMIN_USER_STATUS_CHANGE', "user_id={$targetUserId} -> {$newStatus}");
        }
    } elseif ($action === 'toggle_role') {
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u) abort_request(404, 'ไม่พบบัญชีผู้ใช้นี้');
        if ($u) {
            $newRole = $u['role'] === 'admin' ? 'user' : 'admin';
            $upd = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $upd->bind_param('si', $newRole, $targetUserId);
            $upd->execute();
            $upd->close();
            log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'ADMIN_ROLE_CHANGE', "user_id={$targetUserId} -> {$newRole}");
        }
    }
    $conn->commit();
    flash(empty($errors) ? 'success' : 'error', empty($errors) ? 'อัปเดตบัญชีผู้ใช้เรียบร้อย' : $errors[0]);
    header('Location: manage_users.php'); exit;
}

$errors = ($error = flash('error')) ? [$error] : [];
$successMsg = flash('success');
$users = $conn->query("SELECT user_id, username, email, full_name, role, status, created_at FROM users ORDER BY created_at DESC");

$pageTitle='จัดการผู้ใช้งาน'; require __DIR__ . '/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">People in the community</div><h1 class="page-title">ผู้ใช้งานในระบบ</h1><p class="subtitle">จัดการสิทธิ์และสถานะบัญชี เพื่อดูแลชุมชนให้พร้อมใช้งาน</p></div><span class="count-label"><?= $users->num_rows ?> บัญชี</span></div>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?= e($successMsg) ?></div><?php endif; ?>
<div class="card table-card"><div class="table-wrap"><table><thead><tr><th>ผู้ใช้งาน</th><th>อีเมล</th><th>สิทธิ์</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
<?php while ($user=$users->fetch_assoc()): ?><tr><td><strong><?= e($user['full_name']) ?></strong><div class="note-meta">@<?= e($user['username']) ?> · <?= e(date('d/m/Y',strtotime($user['created_at']))) ?></div></td><td><?= e($user['email']) ?></td><td><span class="badge <?= $user['role']==='admin'?'badge-admin':'' ?>"><?= $user['role']==='admin'?'Admin':'User' ?></span></td><td><span class="badge <?= $user['status']==='active'?'':'badge-suspended' ?>"><?= $user['status']==='active'?'ใช้งานปกติ':'ระงับบัญชี' ?></span></td><td><?php if ((int)$user['user_id'] !== (int)$_SESSION['user_id']): ?><div class="actions"><form method="POST" data-confirm="ยืนยันการเปลี่ยนสถานะบัญชีนี้?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>"><input type="hidden" name="action" value="toggle_status"><button class="btn btn-sm <?= $user['status']==='active'?'btn-danger':'btn-secondary' ?>" type="submit"><?= $user['status']==='active'?'ระงับ':'ปลดระงับ' ?></button></form><form method="POST" data-confirm="ยืนยันการเปลี่ยนสิทธิ์บัญชีนี้?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>"><input type="hidden" name="action" value="toggle_role"><button class="btn btn-sm btn-secondary" type="submit"><?= $user['role']==='admin'?'ลดเป็น User':'ตั้งเป็น Admin' ?></button></form></div><?php else: ?><span class="note-meta">บัญชีของคุณ</span><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></div>
<?php $conn->close(); require __DIR__ . '/footer.php'; ?>
