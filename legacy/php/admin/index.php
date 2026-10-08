<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_admin(); $conn=getDbConnection();
$totalUsers=$conn->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'];
$totalNotes=$conn->query("SELECT COUNT(*) c FROM notes WHERE status='active'")->fetch_assoc()['c'];
$totalComments=$conn->query('SELECT COUNT(*) c FROM comments')->fetch_assoc()['c'];
$totalDownloads=$conn->query('SELECT COUNT(*) c FROM download_logs')->fetch_assoc()['c'];
$recentLogs=$conn->query('SELECT sl.action,sl.detail,sl.ip_address,sl.created_at,sl.username_attempt,u.username FROM security_logs sl LEFT JOIN users u ON sl.user_id=u.user_id ORDER BY sl.created_at DESC,sl.log_id DESC LIMIT 10');
$pageTitle='ภาพรวมระบบ'; require __DIR__ . '/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">Community overview</div><h1 class="page-title">ดูแลพื้นที่การเรียนรู้</h1><p class="subtitle">ภาพรวมผู้ใช้งาน ชีทสรุป และกิจกรรมล่าสุดใน NoteShare</p></div><a class="btn btn-secondary" href="../index.php">ดูหน้าเว็บไซต์ <?= icon('arrow') ?></a></div>
<div class="stat-grid"><?php foreach ([['user',$totalUsers,'ผู้ใช้งานทั้งหมด'],['book',$totalNotes,'ชีทที่เผยแพร่'],['comment',$totalComments,'ความคิดเห็น'],['download',$totalDownloads,'ดาวน์โหลดรวม']] as [$symbol,$value,$label]): ?><div class="stat-box"><span class="stat-icon"><?= icon($symbol) ?></span><div class="num"><?= number_format((int)$value) ?></div><div class="label"><?= $label ?></div></div><?php endforeach; ?></div>
<div class="card table-card"><div class="table-title"><h3>กิจกรรมล่าสุด</h3><a class="text-link" href="security_logs.php">ดูทั้งหมด <?= icon('arrow') ?></a></div><div class="table-wrap"><table><thead><tr><th>ผู้ใช้งาน</th><th>เหตุการณ์</th><th>รายละเอียด</th><th>เวลา</th></tr></thead><tbody>
<?php if (!$recentLogs->num_rows): ?><tr><td colspan="4" class="table-empty">ยังไม่มีบันทึกกิจกรรม</td></tr><?php endif; ?>
<?php while ($log=$recentLogs->fetch_assoc()): ?><tr><td><?= e($log['username'] ?? $log['username_attempt'] ?? 'ผู้เยี่ยมชม') ?></td><td><span class="event-tag <?= in_array($log['action'],['LOGIN_FAILED','ACCOUNT_LOCKOUT'],true)?'failed':'' ?>"><?= e($log['action']) ?></span></td><td class="log-detail"><?= e($log['detail']) ?></td><td class="note-meta"><?= e(date('d/m/Y H:i',strtotime($log['created_at']))) ?></td></tr><?php endwhile; ?></tbody></table></div></div>
<?php $conn->close(); require __DIR__ . '/footer.php'; ?>
