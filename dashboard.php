<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

require_login(); // ข้อ 2: หน้าที่ต้อง Login ไม่สามารถเข้าถึงโดยตรงได้

$conn = getDbConnection();

// ข้อ 3: Authorization - User เห็น/จัดการเฉพาะชีทของตัวเอง
$stmt = $conn->prepare(
    "SELECT note_id, title, file_type, download_count, status, created_at
     FROM notes WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$myNotes = $stmt->get_result();

$pageTitle = 'ชีทของฉัน';
require_once __DIR__ . '/includes/header.php';

$successMsg = flash('success');
$errorMsg = flash('error');
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 class="page-title">ชีทของฉัน</h2>
            <p class="subtitle">จัดการชีทสรุปที่คุณอัปโหลด</p>
        </div>
        <a href="upload.php" class="btn">+ อัปโหลดชีทใหม่</a>
    </div>

    <?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="alert alert-error"><?= e($errorMsg) ?></div><?php endif; ?>

    <?php if ($myNotes->num_rows === 0): ?>
        <p class="subtitle">คุณยังไม่มีชีทที่อัปโหลด</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>ชื่อชีท</th><th>ประเภทไฟล์</th><th>ดาวน์โหลด</th><th>สถานะ</th><th>วันที่</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
            <?php while ($n = $myNotes->fetch_assoc()): ?>
                <tr>
                    <td><a href="note_detail.php?id=<?= (int)$n['note_id'] ?>"><?= e($n['title']) ?></a></td>
                    <td><?= e(strtoupper($n['file_type'])) ?></td>
                    <td><?= (int)$n['download_count'] ?></td>
                    <td><?= $n['status'] === 'active' ? 'ปกติ' : 'ถูกลบโดยแอดมิน' ?></td>
                    <td><?= e(date('d/m/Y', strtotime($n['created_at']))) ?></td>
                    <td class="actions">
                        <a href="edit_note.php?id=<?= (int)$n['note_id'] ?>" class="btn btn-sm btn-secondary">แก้ไข</a>
                        <form action="delete_note.php" method="POST" data-confirm="ยืนยันการลบชีทนี้?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="note_id" value="<?= (int)$n['note_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
require_once __DIR__ . '/includes/footer.php';
?>
