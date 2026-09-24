<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();

$conn = getDbConnection();

// ข้อ 7: Input validation ของค่า filter ที่รับมาจาก query string
$actionFilter = trim($_GET['action'] ?? '');
$allowedActions = ['', 'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT', 'REGISTER', 'NOTE_CREATE',
                    'NOTE_UPDATE', 'NOTE_DELETE', 'FILE_UPLOAD', 'FILE_DOWNLOAD',
                    'COMMENT_DELETE', 'ADMIN_USER_STATUS_CHANGE', 'ADMIN_ROLE_CHANGE'];
if (!in_array($actionFilter, $allowedActions, true)) {
    $actionFilter = '';
}

if ($actionFilter !== '') {
    $stmt = $conn->prepare(
        "SELECT sl.action, sl.detail, sl.ip_address, sl.created_at, sl.username_attempt, u.username
         FROM security_logs sl LEFT JOIN users u ON sl.user_id = u.user_id
         WHERE sl.action = ? ORDER BY sl.created_at DESC LIMIT 200"
    );
    $stmt->bind_param('s', $actionFilter);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query(
        "SELECT sl.action, sl.detail, sl.ip_address, sl.created_at, sl.username_attempt, u.username
         FROM security_logs sl LEFT JOIN users u ON sl.user_id = u.user_id
         ORDER BY sl.created_at DESC LIMIT 200"
    );
}

$pageTitle = 'Security Logs';
require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">Security Logs</h2>
<p class="subtitle">บันทึกเหตุการณ์ด้านความปลอดภัยล่าสุด 200 รายการ (ข้อ 10: Security Logging)</p>

<div class="card">
    <form method="GET" action="security_logs.php" style="margin-bottom:14px;">
        <label for="action">กรองตามประเภทเหตุการณ์</label>
        <select id="action" name="action" onchange="this.form.submit()">
            <option value="">-- ทั้งหมด --</option>
            <?php foreach (array_slice($allowedActions, 1) as $a): ?>
                <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <table>
        <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>เหตุการณ์</th><th>รายละเอียด</th><th>IP Address</th></tr></thead>
        <tbody>
        <?php while ($log = $logs->fetch_assoc()): ?>
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
