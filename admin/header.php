<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Admin | NoteShare</title>
<style>
    :root {
        --primary: #4f46e5; --primary-dark: #4338ca; --bg: #f8fafc; --card-bg: #ffffff;
        --text: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --danger: #dc2626; --success: #16a34a;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', 'Sarabun', Tahoma, sans-serif; background: var(--bg); color: var(--text); }
    nav { background: #1e1b4b; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    nav .brand { font-weight: 700; font-size: 1.2rem; color: #fff; text-decoration: none; }
    nav .links { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    nav a { color: #e0e7ff; text-decoration: none; font-size: 0.95rem; }
    nav a:hover { color: #fff; }
    .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px; }
    .btn { display: inline-block; background: var(--primary); color: #fff; border: none; padding: 9px 18px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.95rem; }
    .btn:hover { background: var(--primary-dark); }
    .btn-danger { background: var(--danger); } .btn-danger:hover { background: #b91c1c; }
    .btn-secondary { background: #64748b; }
    .btn-sm { padding: 5px 12px; font-size: 0.85rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); font-size: 0.88rem; }
    .badge { display: inline-block; background: #eef2ff; color: var(--primary); padding: 2px 10px; border-radius: 20px; font-size: 0.78rem; }
    .badge-admin { background: #fef3c7; color: #92400e; }
    .badge-suspended { background: #fee2e2; color: #991b1b; }
    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 0.92rem; }
    .alert-error { background: #fef2f2; color: var(--danger); border: 1px solid #fecaca; }
    .alert-success { background: #f0fdf4; color: var(--success); border: 1px solid #bbf7d0; }
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; }
    .stat-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: 10px; padding: 18px; text-align: center; }
    .stat-box .num { font-size: 1.8rem; font-weight: 700; color: var(--primary); }
    .stat-box .label { color: var(--text-muted); font-size: 0.85rem; }
    .page-title { margin-bottom: 4px; }
    .subtitle { color: var(--text-muted); margin-top: 0; margin-bottom: 20px; }
</style>
<script src="../assets/confirm.js" defer></script>
</head>
<body>
<nav>
    <a class="brand" href="index.php">🛠 NoteShare Admin</a>
    <div class="links">
        <a href="index.php">แดชบอร์ด</a>
        <a href="manage_users.php">จัดการผู้ใช้</a>
        <a href="manage_notes.php">จัดการชีท</a>
        <a href="security_logs.php">Security Logs</a>
        <a href="../index.php">↩ กลับหน้าเว็บหลัก</a>
        <span style="color:#c7d2fe;font-size:0.9rem;">👤 <?= e($_SESSION['full_name'] ?? '') ?></span>
        <a href="../logout.php" class="btn btn-sm btn-secondary">ออกจากระบบ</a>
    </div>
</nav>
<div class="container">
