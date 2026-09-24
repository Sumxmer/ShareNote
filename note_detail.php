<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

$conn = getDbConnection();

$noteId = (int)($_GET['id'] ?? 0);
if ($noteId <= 0) {
    http_response_code(404);
    die('ไม่พบชีทที่ต้องการ');
}

// ------------------------------------------------------------------
// รับคอมเมนต์ใหม่ (ต้อง Login ก่อน)
// ------------------------------------------------------------------
$commentError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    require_login(); // ข้อ 2
    csrf_verify();   // ข้อ 9

    $content = trim($_POST['content'] ?? '');
    if ($e = validate_comment($content)) {
        $commentError = $e;
    } else {
        $stmt = $conn->prepare("INSERT INTO comments (note_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $noteId, $_SESSION['user_id'], $content);
        $stmt->execute();
        $stmt->close();
        header('Location: note_detail.php?id=' . $noteId . '#comments');
        exit;
    }
}

// ------------------------------------------------------------------
// ดึงข้อมูลชีท (ข้อ 6: Prepared Statement)
// ------------------------------------------------------------------
$stmt = $conn->prepare(
    "SELECT n.*, u.username, u.user_id AS owner_id, s.subject_name
     FROM notes n
     JOIN users u ON n.user_id = u.user_id
     JOIN subjects s ON n.subject_id = s.subject_id
     WHERE n.note_id = ? AND n.status = 'active'"
);
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="card"><p>ไม่พบชีทนี้ หรือถูกลบไปแล้ว</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ดึงคอมเมนต์
$cstmt = $conn->prepare(
    "SELECT c.comment_id, c.content, c.created_at, c.user_id, u.username
     FROM comments c JOIN users u ON c.user_id = u.user_id
     WHERE c.note_id = ? ORDER BY c.created_at ASC"
);
$cstmt->bind_param('i', $noteId);
$cstmt->execute();
$comments = $cstmt->get_result();

$isOwner = !empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$note['owner_id'];

$pageTitle = $note['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <span class="badge"><?= e($note['subject_name']) ?></span>
    <h2 class="page-title" style="margin-top:8px;"><?= e($note['title']) ?></h2>
    <p class="note-meta">
        โดย <?= e($note['username']) ?> ·
        อัปโหลดเมื่อ <?= e(date('d/m/Y H:i', strtotime($note['created_at']))) ?> ·
        <?= (int)$note['download_count'] ?> ดาวน์โหลด ·
        ไฟล์: <?= e(strtoupper($note['file_type'])) ?>
        (<?= number_format($note['file_size'] / 1024, 1) ?> KB)
    </p>

    <?php if (!empty($note['description'])): ?>
        <p style="white-space:pre-line;"><?= e($note['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="download.php?id=<?= (int)$note['note_id'] ?>" class="btn">⬇ ดาวน์โหลดไฟล์</a>
    <?php else: ?>
        <p><a href="login.php">เข้าสู่ระบบ</a> เพื่อดาวน์โหลดไฟล์นี้</p>
    <?php endif; ?>

    <?php if ($isOwner || is_admin()): ?>
        <a href="edit_note.php?id=<?= (int)$note['note_id'] ?>" class="btn btn-secondary">แก้ไข</a>
    <?php endif; ?>
</div>

<div class="card" id="comments">
    <h3>ความคิดเห็น (<?= $comments->num_rows ?>)</h3>

    <?php if (!empty($_SESSION['user_id'])): ?>
        <?php if ($commentError): ?><div class="alert alert-error"><?= e($commentError) ?></div><?php endif; ?>
        <form method="POST" action="note_detail.php?id=<?= (int)$noteId ?>#comments">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_comment">
            <textarea name="content" rows="2" maxlength="1000" placeholder="แสดงความคิดเห็น..." required></textarea>
            <button type="submit" class="btn btn-sm">แสดงความคิดเห็น</button>
        </form>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
    <?php else: ?>
        <p class="subtitle"><a href="login.php">เข้าสู่ระบบ</a>เพื่อแสดงความคิดเห็น</p>
    <?php endif; ?>

    <?php if ($comments->num_rows === 0): ?>
        <p class="subtitle">ยังไม่มีความคิดเห็น</p>
    <?php else: ?>
        <?php while ($c = $comments->fetch_assoc()): ?>
            <div class="comment-box">
                <strong><?= e($c['username']) ?></strong>
                <span class="note-meta"><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></span>
                <?php if (!empty($_SESSION['user_id']) && ((int)$_SESSION['user_id'] === (int)$c['user_id'] || is_admin())): ?>
                    <form action="delete_comment.php" method="POST" style="display:inline; float:right;" data-confirm="ลบความคิดเห็นนี้?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="comment_id" value="<?= (int)$c['comment_id'] ?>">
                        <input type="hidden" name="note_id" value="<?= (int)$noteId ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 8px;">ลบ</button>
                    </form>
                <?php endif; ?>
                <p style="margin:6px 0 0; white-space:pre-line;"><?= e($c['content']) ?></p>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php
$cstmt->close();
$conn->close();
require_once __DIR__ . '/includes/footer.php';
?>
