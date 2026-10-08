<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/csrf.php';

require_admin();

$conn = getDbConnection();
$successMsg = flash('success');

$notes = $conn->query(
    "SELECT n.note_id, n.title, n.status, n.download_count, n.created_at, u.username, s.subject_name
     FROM notes n JOIN users u ON n.user_id = u.user_id JOIN subjects s ON n.subject_id = s.subject_id
     ORDER BY n.created_at DESC"
);

$pageTitle='จัดการชีททั้งหมด'; require __DIR__ . '/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">Shared knowledge</div><h1 class="page-title">ชีทสรุปทั้งหมด</h1><p class="subtitle">ตรวจสอบและจัดการชีทที่สมาชิกแบ่งปันในระบบ</p></div><span class="count-label"><?= $notes->num_rows ?> รายการ</span></div>
<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?= e($successMsg) ?></div><?php endif; ?>
<div class="card table-card"><div class="table-wrap"><table><thead><tr><th>ชีทสรุป</th><th>ผู้แบ่งปัน</th><th>ดาวน์โหลด</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
<?php if (!$notes->num_rows): ?><tr><td colspan="5" class="table-empty">ยังไม่มีชีทในระบบ</td></tr><?php endif; ?>
<?php while ($note=$notes->fetch_assoc()): ?><tr><td><?php if ($note['status']==='active'): ?><a href="../note_detail.php?id=<?= (int)$note['note_id'] ?>"><?= e($note['title']) ?></a><?php else: ?><strong><?= e($note['title']) ?></strong><?php endif; ?><div class="note-meta"><?= e($note['subject_name']) ?> · <?= e(date('d/m/Y',strtotime($note['created_at']))) ?></div></td><td><?= e($note['username']) ?></td><td><?= number_format((int)$note['download_count']) ?></td><td><span class="badge <?= $note['status']==='active'?'':'badge-suspended' ?>"><?= $note['status']==='active'?'เผยแพร่':'ถูกนำออก' ?></span></td><td><?php if ($note['status']==='active'): ?><div class="actions"><a class="btn btn-sm btn-secondary" href="../edit_note.php?id=<?= (int)$note['note_id'] ?>">แก้ไข</a><form action="../delete_note.php" method="POST" data-confirm="ยืนยันการนำชีทนี้ออกจากการเผยแพร่?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="note_id" value="<?= (int)$note['note_id'] ?>"><button type="submit" class="btn btn-sm btn-danger">ลบ</button></form></div><?php else: ?><span class="note-meta">หยุดเผยแพร่แล้ว</span><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></div>
<?php $conn->close(); require __DIR__ . '/footer.php'; ?>
