<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
$conn = getDbConnection();
$keyword = trim(input_text($_GET, 'q'));
if (mb_strlen($keyword) > 100) abort_request(400, 'คำค้นหาต้องไม่เกิน 100 ตัวอักษร');
$subjectId = input_text($_GET, 'subject') === '' ? 0 : input_id($_GET, 'subject');
$page = input_id($_GET, 'page', 1);
$where = "n.status = 'active'";
$params = []; $types = '';
if ($keyword !== '') {
    $where .= ' AND (n.title LIKE ? OR n.description LIKE ? OR s.subject_name LIKE ?)';
    $like = '%' . $keyword . '%'; $params = [$like, $like, $like]; $types = 'sss';
}
if ($subjectId) { $where .= ' AND n.subject_id = ?'; $params[] = $subjectId; $types .= 'i'; }
$from = ' FROM notes n JOIN users u ON n.user_id = u.user_id JOIN subjects s ON n.subject_id = s.subject_id WHERE ' . $where;
$countStmt = $conn->prepare('SELECT COUNT(*) AS total' . $from);
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute(); $total = (int)$countStmt->get_result()->fetch_assoc()['total']; $countStmt->close();
$pages = max(1, (int)ceil($total / 12)); $page = min($page, $pages);
$limit = 12; $offset = ($page - 1) * $limit;
$listParams = [...$params, $limit, $offset]; $listTypes = $types . 'ii';
$stmt = $conn->prepare('SELECT n.note_id, n.title, n.description, n.file_type, n.download_count, n.created_at, u.username, s.subject_name' . $from . ' ORDER BY n.created_at DESC, n.note_id DESC LIMIT ? OFFSET ?');
$stmt->bind_param($listTypes, ...$listParams); $stmt->execute(); $notes = $stmt->get_result();
$subjects = $conn->query("SELECT s.subject_id, s.subject_name FROM subjects s WHERE EXISTS (SELECT 1 FROM notes n WHERE n.subject_id=s.subject_id AND n.status='active') ORDER BY s.subject_name");
$pageTitle = 'สำรวจชีทสรุป';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
<div><div class="eyebrow">Your shared study space</div><h1>ชีทเล็ก ๆ ของคุณ<br><span>อาจช่วยใครได้อีกมาก</span></h1><p>พื้นที่รวมชีทสรุปจากเพื่อน ๆ ค้นหาวิชาที่กำลังเรียน<br>ทบทวนก่อนสอบ และส่งต่อความรู้ในแบบของคุณ</p><div class="hero-actions"><a href="<?= empty($_SESSION['user_id']) ? 'register.php' : 'upload.php' ?>" class="btn"><?= icon('upload') ?> เริ่มแบ่งปันชีท</a><a href="#library" class="text-link">ค้นหาชีทที่ต้องการ <?= icon('arrow') ?></a></div></div>
<?php require __DIR__ . '/includes/paper_art.php'; ?>
</section>
<form method="GET" action="index.php" class="search-panel" id="library">
<div class="search-field"><label class="sr-only" for="q">ค้นหาชื่อชีท คำอธิบาย หรือรายวิชา</label><?= icon('search') ?><input type="search" id="q" name="q" value="<?= e($keyword) ?>" placeholder="วันนี้อยากเรียนรู้อะไร? ค้นหาชีทหรือรายวิชา…" maxlength="100"></div>
<label class="sr-only" for="subject">รายวิชา</label><select id="subject" name="subject"><option value="">ทุกรายวิชา</option><?php while ($subject = $subjects->fetch_assoc()): ?><option value="<?= (int)$subject['subject_id'] ?>"<?= $subjectId === (int)$subject['subject_id'] ? ' selected' : '' ?>><?= e($subject['subject_name']) ?></option><?php endwhile; ?></select><button type="submit" class="btn">ค้นหา</button>
</form>
<div class="section-heading"><div><h2><?= $keyword !== '' || $subjectId ? 'ผลการค้นหา' : 'ชีทสรุปล่าสุด' ?></h2><p><?= $keyword !== '' ? 'ผลลัพธ์สำหรับ “' . e($keyword) . '”' : 'ความรู้ที่เพื่อน ๆ พร้อมแบ่งปันให้คุณ' ?></p></div><span class="count-label"><?= number_format($total) ?> ชีท</span></div>
<?php if (!$total): ?>
<div class="empty-state"><div class="empty-icon"><?= icon('search') ?></div><h3><?= $keyword !== '' || $subjectId ? 'ยังไม่พบชีทที่ค้นหา' : 'พื้นที่นี้รอชีทแรกจากคุณ' ?></h3><p><?= $keyword !== '' || $subjectId ? 'ลองเปลี่ยนคำค้นหาหรือเลือกดูทุกรายวิชา' : 'แบ่งปันสรุปที่คุณตั้งใจทำ ให้เป็นประโยชน์กับเพื่อน ๆ' ?></p><a href="<?= $keyword !== '' || $subjectId ? 'index.php' : 'upload.php' ?>" class="btn btn-secondary"><?= $keyword !== '' || $subjectId ? 'ดูชีททั้งหมด' : 'แบ่งปันชีทแรก' ?></a></div>
<?php else: ?>
<div class="note-grid">
<?php while ($note = $notes->fetch_assoc()): ?>
<article class="note-card"><div class="note-card-top"><span class="file-icon <?= file_badge($note['file_type']) ?>"><?= e(strtoupper($note['file_type'])) ?></span><span class="badge" title="<?= e($note['subject_name']) ?>"><?= e($note['subject_name']) ?></span></div><h3><a href="note_detail.php?id=<?= (int)$note['note_id'] ?>"><?= e($note['title']) ?></a></h3><p class="note-summary"><?= e($note['description'] ?: 'เปิดดูรายละเอียดและดาวน์โหลดชีทนี้') ?></p><div class="note-card-footer"><span><?= icon('user') ?> <?= e($note['username']) ?></span><span><?= icon('download') ?> <?= (int)$note['download_count'] ?></span><a href="note_detail.php?id=<?= (int)$note['note_id'] ?>" aria-label="<?= e('ดูชีท ' . $note['title']) ?>"><?= icon('arrow') ?></a></div></article>
<?php endwhile; ?>
</div>
<?php if ($pages > 1): ?><nav class="pagination" aria-label="หน้าผลการค้นหา"><?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(['q'=>$keyword,'subject'=>$subjectId ?: '', 'page'=>$page-1])) ?>#library">ก่อนหน้า</a><?php endif; ?><span>หน้า <?= $page ?> จาก <?= $pages ?></span><?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(['q'=>$keyword,'subject'=>$subjectId ?: '', 'page'=>$page+1])) ?>#library">ถัดไป <?= icon('arrow') ?></a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php $stmt->close(); $conn->close(); require __DIR__ . '/includes/footer.php'; ?>
