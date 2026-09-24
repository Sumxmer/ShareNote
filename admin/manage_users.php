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

    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // ป้องกันแอดมินระงับ/ลดสิทธิ์ตัวเอง โดยไม่ตั้งใจจนล็อกตัวเองออก
    if ($targetUserId === (int)$_SESSION['user_id']) {
        $errors[] = 'ไม่สามารถเปลี่ยนสถานะ/สิทธิ์ของบัญชีตัวเองได้';
    } elseif ($action === 'toggle_status') {
        $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($u) {
            $newStatus = $u['status'] === 'active' ? 'suspended' : 'active';
            $upd = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $upd->bind_param('si', $newStatus, $targetUserId);
            $upd->execute();
            $upd->close();
            log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'ADMIN_USER_STATUS_CHANGE', "user_id={$targetUserId} -> {$newStatus}");
        }
    } elseif ($action === 'toggle_role') {
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($u) {
            $newRole = $u['role'] === 'admin' ? 'user' : 'admin';
            $upd = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $upd->bind_param('si', $newRole, $targetUserId);
            $upd->execute();
            $upd->close();
            log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'ADMIN_ROLE_CHANGE', "user_id={$targetUserId} -> {$newRole}");
        }
    }
}

$users = $conn->query("SELECT user_id, username, email, full_name, role, status, created_at FROM users ORDER BY created_at DESC");

$pageTitle = 'จัดการผู้ใช้';
require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">จัดการผู้ใช้ทั้งหมด</h2>
<p class="subtitle">Admin สามารถดูและจัดการข้อมูลผู้ใช้ทั้งหมดในระบบได้</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr><th>Username</th><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>สิทธิ์</th><th>สถานะ</th><th>สมัครเมื่อ</th><th>จัดการ</th></tr>
        </thead>
        <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : '' ?>">
                        <?= $u['role'] === 'admin' ? 'Admin' : 'User' ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $u['status'] === 'suspended' ? 'badge-suspended' : '' ?>">
                        <?= $u['status'] === 'active' ? 'ปกติ' : 'ถูกระงับ' ?>
                    </span>
                </td>
                <td><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                <td>
                    <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline;" data-confirm="ยืนยันการเปลี่ยนสถานะ?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>">
                                <?= $u['status'] === 'active' ? 'ระงับ' : 'ปลดระงับ' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;" data-confirm="ยืนยันการเปลี่ยนสิทธิ์?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                            <input type="hidden" name="action" value="toggle_role">
                            <button type="submit" class="btn btn-sm btn-secondary">
                                <?= $u['role'] === 'admin' ? 'ลดเป็น User' : 'ตั้งเป็น Admin' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="note-meta">(บัญชีของฉัน)</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
require_once __DIR__ . '/footer.php';
?>
