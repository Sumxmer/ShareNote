<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();

$conn = getDbConnection();

// ข้อ 7: Input validation ของค่า filter ที่รับมาจาก query string
$actionFilter = trim(input_text($_GET, 'action'));
$allowedActions = ['', 'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT', 'REGISTER', 'NOTE_CREATE',
                    'NOTE_UPDATE', 'NOTE_DELETE', 'FILE_UPLOAD', 'FILE_DOWNLOAD',
                    'COMMENT_CREATE', 'COMMENT_DELETE', 'ACCOUNT_LOCKOUT', 'ADMIN_USER_STATUS_CHANGE', 'ADMIN_ROLE_CHANGE'];
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

$pageTitle='บันทึกเหตุการณ์'; require __DIR__ . '/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">Security & activity</div><h1 class="page-title">บันทึกเหตุการณ์</h1><p class="subtitle">ตรวจสอบกิจกรรมและเหตุการณ์ด้านความปลอดภัยล่าสุด 200 รายการ</p></div></div>
<div class="card table-card"><div class="table-title"><h3>ประวัติกิจกรรม</h3><form method="GET" action="security_logs.php" class="filter-form"><label for="action"><span class="sr-only">กรองตามประเภทเหตุการณ์</span><select id="action" name="action" data-submit><option value="">ทุกประเภทเหตุการณ์</option><?php foreach(array_slice($allowedActions,1) as $action): ?><option value="<?= e($action) ?>"<?= $actionFilter===$action?' selected':'' ?>><?= e($action) ?></option><?php endforeach; ?></select></label><button class="btn btn-sm btn-secondary" type="submit">กรอง</button></form></div><div class="table-wrap"><table><thead><tr><th>เวลา</th><th>ผู้ใช้งาน</th><th>เหตุการณ์</th><th>รายละเอียด</th><th>IP Address</th></tr></thead><tbody>
<?php if (!$logs->num_rows): ?><tr><td colspan="5" class="table-empty">ยังไม่มีเหตุการณ์ประเภทนี้</td></tr><?php endif; ?>
<?php while($log=$logs->fetch_assoc()): ?><tr><td class="note-meta"><?= e(date('d/m/Y H:i:s',strtotime($log['created_at']))) ?></td><td><?= e($log['username']??$log['username_attempt']??'ผู้เยี่ยมชม') ?></td><td><span class="event-tag <?= in_array($log['action'],['LOGIN_FAILED','ACCOUNT_LOCKOUT'],true)?'failed':'' ?>"><?= e($log['action']) ?></span></td><td class="log-detail"><?= e($log['detail']) ?></td><td class="note-meta"><?= e($log['ip_address']) ?></td></tr><?php endwhile; ?></tbody></table></div></div>
<?php if (isset($stmt)) $stmt->close(); $conn->close(); require __DIR__ . '/footer.php'; ?>
