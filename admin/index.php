<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/csrf.php';

require_admin(); // ข้อ 3: เฉพาะ Admin เท่านั้นที่เข้าหน้านี้ได้

$conn = getDbConnection();

$totalUsers = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$totalNotes = $conn->query("SELECT COUNT(*) c FROM notes WHERE status='active'")->fetch_assoc()['c'];
$totalComments = $conn->query("SELECT COUNT(*) c FROM comments")->fetch_assoc()['c'];
$totalDownloads = $conn->query("SELECT COUNT(*) c FROM download_logs")->fetch_assoc()['c'];

$recentLogs = $conn->query(
    "SELECT sl.action, sl.detail, sl.ip_address, sl.created_at, sl.username_attempt, u.username
     FROM security_logs sl LEFT JOIN users u ON sl.user_id = u.user_id
     ORDER BY sl.created_at DESC LIMIT 10"
);

$pageTitle = 'แดชบอร์ดผู้ดูแลระบบ';
require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">ภาพรวมระบบ</h2>
<p class="subtitle">สรุปสถิติการใช้งานทั้งหมดของระบบ NoteShare</p>

<div class="stat-grid">
    <div class="stat-box"><div class="num"><?= (int)$totalUsers ?></div><div class="label">ผู้ใช้ทั้งหมด</div></div>
    <div class="stat-box"><div class="num"><?= (int)$totalNotes ?></div><div class="label">ชีทที่ใช้งานอยู่</div></div>
    <div class="stat-box"><div class="num"><?= (int)$totalComments ?></div><div class="label">คอมเมนต์ทั้งหมด</div></div>
    <div class="stat-box"><div class="num"><?= (int)$totalDownloads ?></div><div class="label">ยอดดาวน์โหลดรวม</div></div>
</div>

<div class="card" style="margin-top:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3 style="margin:0;">เหตุการณ์ล่าสุด (Security Logs)</h3>
        <a href="security_logs.php" class="btn btn-sm">ดูทั้งหมด</a>
    </div>
    <table style="margin-top:12px;">
        <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>เหตุการณ์</th><th>รายละเอียด</th><th>IP</th></tr></thead>
        <tbody>
        <?php while ($log = $recentLogs->fetch_assoc()): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
                <td><?= e($log['username'] ?? $log['username_attempt'] ?? '-') ?></td>
                <td><?= e($log['action']) ?></td>
                <td><?= e($log['detail']) ?></td>
                <td><?= e($log['ip_address']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
require_once __DIR__ . '/footer.php';
?>
