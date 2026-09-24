<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';

$conn = getDbConnection();

// ข้อ 7: Input Validation ของค่าที่รับจาก query string
$keyword = trim($_GET['q'] ?? '');

if (mb_strlen($keyword) > 100) {
    $keyword = mb_substr($keyword, 0, 100);
}

// ข้อ 6: ป้องกัน SQL Injection ด้วย Prepared Statement แม้เป็นแค่การค้นหา/filter
// ค้นหาในชื่อชีท คำอธิบาย และชื่อรายวิชา
$sql = "SELECT n.note_id, n.title, n.description, n.file_type, n.download_count, n.created_at,
               u.username, s.subject_name
        FROM notes n
        JOIN users u ON n.user_id = u.user_id
        JOIN subjects s ON n.subject_id = s.subject_id
        WHERE n.status = 'active'";

$params = [];
$types  = '';

if ($keyword !== '') {
    $sql .= " AND (n.title LIKE ? OR n.description LIKE ? OR s.subject_name LIKE ?)";
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $types .= 'sss';
}

$sql .= " ORDER BY n.created_at DESC LIMIT 50";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$notes = $stmt->get_result();

$pageTitle = 'หน้าแรก';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2 class="page-title">ค้นหาชีทสรุป</h2>
    <form method="GET" action="index.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:2; min-width:200px;">
            <label for="q">คำค้นหา (ชื่อชีท/คำอธิบาย/รายวิชา)</label>
            <input type="search" id="q" name="q" value="<?= e($keyword) ?>" placeholder="เช่น Database, Networks, โครงสร้างข้อมูล..." maxlength="100">
        </div>
        <div>
            <button type="submit" class="btn" style="margin-bottom:14px;">ค้นหา</button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="page-title">ชีทสรุปล่าสุด</h2>
    <?php if ($notes->num_rows === 0): ?>
        <p class="subtitle">ไม่พบชีทที่ตรงกับเงื่อนไข</p>
    <?php else: ?>
        <?php while ($n = $notes->fetch_assoc()): ?>
            <div class="note-item">
                <div>
                    <a class="note-title" href="note_detail.php?id=<?= (int)$n['note_id'] ?>">
                        <?= e($n['title']) ?>
                    </a>
                    <div class="note-meta">
                        <span class="badge"><?= e($n['subject_name']) ?></span>
                        โดย <?= e($n['username']) ?> ·
                        <?= (int)$n['download_count'] ?> ดาวน์โหลด ·
                        <?= e(date('d/m/Y', strtotime($n['created_at']))) ?>
                    </div>
                </div>
                <a href="note_detail.php?id=<?= (int)$n['note_id'] ?>" class="btn btn-sm">ดูรายละเอียด</a>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
require_once __DIR__ . '/includes/footer.php';
?>
