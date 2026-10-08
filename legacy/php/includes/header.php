<?php
require_once __DIR__ . '/../config/csrf.php';
$adminLayout = $adminLayout ?? false;
$prefix = $adminLayout ? '../' : '';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#216653">
<meta name="description" content="NoteShare พื้นที่แบ่งปันชีทสรุปและความรู้สำหรับการเรียนและติวสอบ">
<title><?= e($pageTitle ?? 'แบ่งปันความรู้') ?> · <?= $adminLayout ? 'Admin · ' : '' ?>NoteShare</title>
<link rel="icon" href="<?= $prefix ?>assets/logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= $prefix ?>assets/style.css">
<script src="<?= $prefix ?>assets/app.js" defer></script>
</head>
<body<?= $adminLayout ? ' class="is-admin"' : '' ?>>
<a class="skip-link" href="#main">ข้ามไปเนื้อหา</a>
<header class="site-nav">
<div class="nav-inner">
<a class="brand" href="<?= $prefix ?>index.php"><img src="<?= $prefix ?>assets/logo.svg" alt="">NoteShare<?php if ($adminLayout): ?><small>ADMIN</small><?php endif; ?></a>
<nav class="nav-links" aria-label="เมนูหลัก">
<a href="<?= $prefix ?>index.php"<?= !$adminLayout && $currentPage === 'index.php' ? ' class="active" aria-current="page"' : '' ?>>สำรวจชีทสรุป</a>
<?php if (!empty($_SESSION['user_id'])): ?>
<a href="<?= $prefix ?>dashboard.php"<?= $currentPage === 'dashboard.php' ? ' class="active" aria-current="page"' : '' ?>>ชีทของฉัน</a>
<a href="<?= $prefix ?>upload.php"<?= $currentPage === 'upload.php' ? ' class="active" aria-current="page"' : '' ?>>แบ่งปันชีท</a>
<?php if (is_admin()): ?><a href="<?= $prefix ?>admin/index.php"<?= $adminLayout ? ' class="active"' : '' ?>>ดูแลระบบ</a><?php endif; ?>
<?php endif; ?>
</nav>
<div class="nav-account">
<?php if (!empty($_SESSION['user_id'])): ?>
<span class="avatar" aria-hidden="true"><?= e(mb_substr($_SESSION['full_name'], 0, 1)) ?></span><span class="user-name"><?= e($_SESSION['full_name']) ?></span>
<form action="<?= $prefix ?>logout.php" method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit" class="btn btn-ghost" aria-label="ออกจากระบบ"><?= icon('logout') ?></button></form>
<?php else: ?>
<a href="<?= $prefix ?>login.php" class="text-link">เข้าสู่ระบบ</a><a href="<?= $prefix ?>register.php" class="btn btn-sm">เริ่มต้นใช้งาน <?= icon('arrow') ?></a>
<?php endif; ?>
</div>
</div>
</header>
<main class="container" id="main">
<?php if ($adminLayout): ?>
<nav class="admin-tabs" aria-label="เมนูผู้ดูแลระบบ">
<?php foreach (['index.php'=>'ภาพรวม', 'manage_users.php'=>'ผู้ใช้งาน', 'manage_notes.php'=>'ชีททั้งหมด', 'security_logs.php'=>'บันทึกเหตุการณ์'] as $url=>$label): ?>
<a href="<?= $url ?>"<?= $currentPage === $url ? ' class="active" aria-current="page"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
