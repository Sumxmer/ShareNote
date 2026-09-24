<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>NoteShare | เว็บแชร์ชีทติวสอบ</title>
<style>
    :root {
        --primary: #4f46e5;
        --primary-dark: #4338ca;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --text: #1e293b;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --danger: #dc2626;
        --success: #16a34a;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Segoe UI', 'Sarabun', Tahoma, sans-serif;
        background: var(--bg);
        color: var(--text);
    }
    nav {
        background: var(--card-bg);
        border-bottom: 1px solid var(--border);
        padding: 14px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    nav .brand { font-weight: 700; font-size: 1.2rem; color: var(--primary); text-decoration: none; }
    nav .links { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    nav a { color: var(--text); text-decoration: none; font-size: 0.95rem; }
    nav a:hover { color: var(--primary); }
    .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
    .card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 16px;
    }
    .btn {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        border: none;
        padding: 9px 18px;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.95rem;
    }
    .btn:hover { background: var(--primary-dark); }
    .btn-danger { background: var(--danger); }
    .btn-danger:hover { background: #b91c1c; }
    .btn-secondary { background: #64748b; }
    .btn-sm { padding: 5px 12px; font-size: 0.85rem; }
    input[type=text], input[type=email], input[type=password], input[type=search], textarea, select {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.95rem;
        margin-top: 4px;
        margin-bottom: 14px;
        font-family: inherit;
    }
    label { font-weight: 600; font-size: 0.9rem; }
    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 0.92rem; }
    .alert-error { background: #fef2f2; color: var(--danger); border: 1px solid #fecaca; }
    .alert-success { background: #f0fdf4; color: var(--success); border: 1px solid #bbf7d0; }
    .note-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border); }
    .note-item:last-child { border-bottom: none; }
    .note-title { font-weight: 600; }
    .note-meta { font-size: 0.82rem; color: var(--text-muted); }
    .badge { display: inline-block; background: #eef2ff; color: var(--primary); padding: 2px 10px; border-radius: 20px; font-size: 0.78rem; }
    .comment-box { background: #f8fafc; padding: 10px 14px; border-radius: 8px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
    .actions form { display: inline; }
    .page-title { margin-bottom: 4px; }
    .subtitle { color: var(--text-muted); margin-top: 0; margin-bottom: 20px; }
</style>
<script src="assets/confirm.js" defer></script>
</head>
<body>
<nav>
    <a class="brand" href="index.php">📚 NoteShare</a>
    <div class="links">
        <a href="index.php">หน้าแรก</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="dashboard.php">ชีทของฉัน</a>
            <a href="upload.php">+ อัปโหลดชีท</a>
            <?php if (is_admin()): ?>
                <a href="admin/index.php">🛠 Admin</a>
            <?php endif; ?>
            <span style="color:#64748b;font-size:0.9rem;">สวัสดี, <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
            <a href="logout.php" class="btn btn-sm btn-secondary">ออกจากระบบ</a>
        <?php else: ?>
            <a href="login.php">เข้าสู่ระบบ</a>
            <a href="register.php" class="btn btn-sm">สมัครสมาชิก</a>
        <?php endif; ?>
    </div>
</nav>
<div class="container">
