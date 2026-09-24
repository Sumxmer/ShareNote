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

$pageTitle = 'จัดการชีททั้งหมด';
require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">จัดการชีททั้งหมดในระบบ</h2>
<p class="subtitle">Admin สามารถดูและลบชีทของผู้ใช้ทุกคนได้ (ข้อ 3: Authorization)</p>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>

<div class="card">
    <table>
        <thead><tr><th>ชื่อชีท</th><th>เจ้าของ</th><th>วิชา</th><th>ดาวน์โหลด</th><th>สถานะ</th><th>วันที่</th><th>จัดการ</th></tr></thead>
        <tbody>
        <?php while ($n = $notes->fetch_assoc()): ?>
            <tr>
                <td><a href="../note_detail.php?id=<?= (int)$n['note_id'] ?>"><?= e($n['title']) ?></a></td>
                <td><?= e($n['username']) ?></td>
                <td><?= e($n['subject_name']) ?></td>
                <td><?= (int)$n['download_count'] ?></td>
                <td><?= $n['status'] === 'active' ? 'ปกติ' : 'ถูกลบ' ?></td>
                <td><?= e(date('d/m/Y', strtotime($n['created_at']))) ?></td>
                <td>
                    <?php if ($n['status'] === 'active'): ?>
                        <form action="../delete_note.php" method="POST" data-confirm="ยืนยันการลบชีทนี้ (โดย Admin)?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="note_id" value="<?= (int)$n['note_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    <?php else: ?>
                        <span class="note-meta">-</span>
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
