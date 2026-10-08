<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';
require_login(); $conn = getDbConnection();
$stmt = $conn->prepare('SELECT note_id, title, file_type, download_count, status, created_at FROM notes WHERE user_id = ? ORDER BY created_at DESC, note_id DESC');
$stmt->bind_param('i', $_SESSION['user_id']); $stmt->execute(); $myNotes = $stmt->get_result();
$statsStmt = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='active'),0) AS active, COALESCE(SUM(download_count),0) AS downloads FROM notes WHERE user_id = ?");
$statsStmt->bind_param('i', $_SESSION['user_id']); $statsStmt->execute(); $stats = $statsStmt->get_result()->fetch_assoc(); $statsStmt->close();
$cstmt = $conn->prepare('SELECT COUNT(*) AS total FROM comments c JOIN notes n ON c.note_id=n.note_id WHERE n.user_id=?');
$cstmt->bind_param('i', $_SESSION['user_id']); $cstmt->execute(); $commentCount = $cstmt->get_result()->fetch_assoc()['total']; $cstmt->close();
$successMsg = flash('success'); $errorMsg = flash('error'); $pageTitle = 'ชีทของฉัน'; require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">My study shelf</div><h1 class="page-title">ชีทของฉัน</h1><p class="subtitle">ทุกสรุปที่คุณแบ่งปัน ช่วยให้ใครอีกคนเรียนรู้ได้ง่ายขึ้น</p></div><a class="btn" href="upload.php"><?= icon('upload') ?> แบ่งปันชีทใหม่</a></div>
<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-error" role="alert"><?= e($errorMsg) ?></div><?php endif; ?>
<div class="stat-grid">
<?php foreach ([['file',$stats['total'],'ชีททั้งหมด'],['book',$stats['active'],'กำลังเผยแพร่'],['download',$stats['downloads'],'ดาวน์โหลดรวม'],['comment',$commentCount,'ความคิดเห็น']] as [$symbol,$value,$label]): ?><div class="stat-box"><span class="stat-icon"><?= icon($symbol) ?></span><div class="num"><?= number_format((int)$value) ?></div><div class="label"><?= $label ?></div></div><?php endforeach; ?>
</div>
<?php if (!$myNotes->num_rows): ?><div class="empty-state"><div class="empty-icon"><?= icon('book') ?></div><h3>เริ่มเก็บเรื่องราวการเรียนรู้ของคุณ</h3><p>อัปโหลดชีทแรก แล้วจัดการทุกสรุปของคุณได้จากหน้านี้</p><a class="btn" href="upload.php"><?= icon('upload') ?> แบ่งปันชีทแรก</a></div>
<?php else: ?><div class="card table-card"><div class="table-title"><h3>ชีทที่ฉันแบ่งปัน</h3><span class="count-label"><?= $myNotes->num_rows ?> รายการ</span></div><div class="table-wrap"><table><thead><tr><th>ชีทสรุป</th><th>ดาวน์โหลด</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
<?php while ($note = $myNotes->fetch_assoc()): ?><tr><td><?php if ($note['status'] === 'active'): ?><a href="note_detail.php?id=<?= (int)$note['note_id'] ?>"><?= e($note['title']) ?></a><?php else: ?><strong><?= e($note['title']) ?></strong><?php endif; ?><div class="note-meta"><?= e(strtoupper($note['file_type'])) ?> · <?= e(date('d/m/Y', strtotime($note['created_at']))) ?></div></td><td><?= number_format((int)$note['download_count']) ?></td><td><span class="badge <?= $note['status'] === 'active' ? '' : 'badge-suspended' ?>"><?= $note['status'] === 'active' ? 'เผยแพร่' : 'ถูกนำออก' ?></span></td><td><?php if ($note['status'] === 'active'): ?><div class="actions"><a class="btn btn-sm btn-secondary" href="edit_note.php?id=<?= (int)$note['note_id'] ?>">แก้ไข</a><form action="delete_note.php" method="POST" data-confirm="ยืนยันการนำชีทนี้ออกจากการเผยแพร่?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="note_id" value="<?= (int)$note['note_id'] ?>"><button class="btn btn-sm btn-danger" type="submit">ลบ</button></form></div><?php else: ?><span class="note-meta">หยุดเผยแพร่แล้ว</span><?php endif; ?></td></tr><?php endwhile; ?>
</tbody></table></div></div><?php endif; ?>
<?php $stmt->close(); $conn->close(); require __DIR__ . '/includes/footer.php'; ?>
