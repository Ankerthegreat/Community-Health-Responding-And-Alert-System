<?php
// ── Header partial — included on every protected page ──
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$user = currentUser();
$role = $user['role'] ?? '';

// Build nav links per role
$navLinks = [];
if ($role === 'resident') {
    $navLinks = [
        ['href'=>'report.php',  'label'=>'Submit Report', 'icon'=>'📋'],
        ['href'=>'my_reports.php','label'=>'My Reports',  'icon'=>'📁'],
    ];
} elseif ($role === 'officer') {
    $navLinks = [
        ['href'=>'dashboard.php','label'=>'Dashboard',    'icon'=>'🏥'],
        ['href'=>'reports.php',  'label'=>'All Reports',  'icon'=>'📋'],
        ['href'=>'alerts.php',   'label'=>'Send Alert',   'icon'=>'🚨'],
    ];
} elseif ($role === 'admin') {
    $navLinks = [
        ['href'=>'admin.php',    'label'=>'Admin Panel',  'icon'=>'⚙️'],
        ['href'=>'dashboard.php','label'=>'Dashboard',    'icon'=>'📊'],
        ['href'=>'reports.php',  'label'=>'All Reports',  'icon'=>'📋'],
        ['href'=>'alerts.php',   'label'=>'Alerts',       'icon'=>'🚨'],
        ['href'=>'users.php',    'label'=>'Users',        'icon'=>'👥'],
        ['href'=>'logs.php',     'label'=>'Logs',         'icon'=>'🧾'],
    ];
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CHRAS — <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<canvas id="bgCanvas"></canvas>
<div class="page-wrap">

<!-- TOP BAR -->
<header class="topbar">
  <div class="topbar-logo">CHRAS</div>

  <?php if ($user): ?>
  <nav class="topbar-nav">
    <?php foreach ($navLinks as $link): ?>
    <a href="<?= $link['href'] ?>"
       class="nav-btn <?= $currentPage === $link['href'] ? 'active' : '' ?>">
      <?= $link['icon'] ?> <?= $link['label'] ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="topbar-right">
    <div class="user-chip">
      <span class="role-dot"></span>
      <span><?= htmlspecialchars($user['name']) ?></span>
      <span style="color:var(--text-dim);font-size:0.72rem;margin-left:2px;">[<?= strtoupper($role) ?>]</span>
    </div>
    <a href="logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
  <?php endif; ?>
</header>

<!-- THREE.JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
