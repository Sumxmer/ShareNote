<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/csrf.php';

$conn = getDbConnection();

$noteId = input_id($_GET, 'id');
if ($noteId <= 0) {
    abort_request(404, 'ไม่พบชีทที่ต้องการ');
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

// ------------------------------------------------------------------
// รับคอมเมนต์ใหม่ (ต้อง Login ก่อน)
// ------------------------------------------------------------------
$commentError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); // ข้อ 2
    csrf_verify();
    if (input_text($_POST, 'action') !== 'add_comment') abort_request(400, 'คำขอไม่ถูกต้อง');

    $content = trim(input_text($_POST, 'content'));
    if ($e = validate_comment($content)) {
        $commentError = $e;
    } else {
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO comments (note_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $noteId, $_SESSION['user_id'], $content);
        $stmt->execute();
        $stmt->close();
        log_security_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'COMMENT_CREATE', "เพิ่มคอมเมนต์ note_id={$noteId}");
        $conn->commit();
        header('Location: note_detail.php?id=' . $noteId . '#comments');
        exit;
    }
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

$pageTitle = $note['title']; require __DIR__ . '/includes/header.php';
?>
<div class="breadcrumb"><a href="index.php">สำรวจชีทสรุป</a><span>/</span><span><?= e($note['subject_name']) ?></span></div>
<div class="detail-layout"><div><article class="card"><span class="badge"><?= e($note['subject_name']) ?></span><h1 class="detail-title"><?= e($note['title']) ?></h1><div class="detail-author"><span class="avatar" aria-hidden="true"><?= e(mb_substr($note['username'],0,1)) ?></span><span>แบ่งปันโดย <strong><?= e($note['username']) ?></strong><br><span class="note-meta"><?= e(date('d/m/Y H:i',strtotime($note['created_at']))) ?></span></span></div><div class="detail-body"><?= e($note['description'] ?: 'ผู้แบ่งปันยังไม่ได้เพิ่มคำอธิบายสำหรับชีทนี้') ?></div><?php if ($isOwner || is_admin()): ?><a class="btn btn-secondary btn-sm" href="edit_note.php?id=<?= (int)$noteId ?>"><?= icon('file') ?> แก้ไขรายละเอียดชีท</a><?php endif; ?></article>
<section class="card" id="comments"><div class="section-heading" style="margin-top:0"><h2>พูดคุยเกี่ยวกับชีทนี้</h2><span class="count-label"><?= $comments->num_rows ?> ความคิดเห็น</span></div>
<?php if (!empty($_SESSION['user_id'])): ?>
<?php if ($commentError): ?><div class="alert alert-error" role="alert"><?= e($commentError) ?></div><?php endif; ?>
<form class="comment-form" method="POST" action="note_detail.php?id=<?= (int)$noteId ?>#comments"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_comment"><label class="sr-only" for="content">ความคิดเห็นของคุณ</label><textarea name="content" id="content" rows="3" maxlength="1000" placeholder="ขอบคุณผู้แบ่งปัน หรือฝากคำถามเกี่ยวกับเนื้อหา…" required></textarea><button type="submit" class="btn btn-sm"><?= icon('comment') ?> ส่งความคิดเห็น</button></form>
<?php else: ?><p class="subtitle"><a href="login.php">เข้าสู่ระบบ</a> เพื่อร่วมพูดคุยและขอบคุณผู้แบ่งปัน</p><?php endif; ?>
<?php if (!$comments->num_rows): ?><p class="note-meta">ยังไม่มีความคิดเห็น มาเริ่มบทสนทนาดี ๆ กัน</p><?php endif; ?>
<?php while ($comment=$comments->fetch_assoc()): ?><article class="comment-box"><div class="comment-top"><span class="avatar" aria-hidden="true"><?= e(mb_substr($comment['username'],0,1)) ?></span><strong><?= e($comment['username']) ?></strong><time datetime="<?= e(date('c',strtotime($comment['created_at']))) ?>"><?= e(date('d/m/Y H:i',strtotime($comment['created_at']))) ?></time><?php if (!empty($_SESSION['user_id']) && ((int)$_SESSION['user_id']===(int)$comment['user_id'] || is_admin())): ?><form action="delete_comment.php" method="POST" data-confirm="ยืนยันการลบความคิดเห็นนี้?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['comment_id'] ?>"><button class="btn btn-sm btn-ghost" type="submit">ลบ</button></form><?php endif; ?></div><p><?= e($comment['content']) ?></p></article><?php endwhile; ?>
</section></div>
<aside class="download-card"><span class="file-icon <?= file_badge($note['file_type']) ?>"><?= e(strtoupper($note['file_type'])) ?></span><h3>ไฟล์ชีทสรุป</h3><p class="file-name"><?= e($note['original_file_name']) ?></p><div class="info-row"><span>ประเภทไฟล์</span><strong><?= e(strtoupper($note['file_type'])) ?></strong></div><div class="info-row"><span>ขนาดไฟล์</span><strong><?= format_bytes((int)$note['file_size']) ?></strong></div><div class="info-row"><span>ดาวน์โหลดแล้ว</span><strong><?= number_format((int)$note['download_count']) ?> ครั้ง</strong></div>
<?php if (!empty($_SESSION['user_id'])): ?><a class="btn" href="download.php?id=<?= (int)$noteId ?>"><?= icon('download') ?> ดาวน์โหลดชีท</a><?php else: ?><a class="btn" href="login.php">เข้าสู่ระบบเพื่อดาวน์โหลด <?= icon('arrow') ?></a><?php endif; ?><p class="mini-note"><?= icon('book') ?> เก็บไว้อ่าน และส่งต่อสิ่งที่คุณได้เรียนรู้</p></aside>
</div>
<?php $cstmt->close(); $conn->close(); require __DIR__ . '/includes/footer.php'; ?>
