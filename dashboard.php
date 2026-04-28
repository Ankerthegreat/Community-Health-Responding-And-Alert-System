<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('officer','admin');
$pageTitle = 'Dashboard';
$db        = db();

// ── Stats ────────────────────────────────────────────
$total    = $db->query("SELECT COUNT(*) FROM reports")->fetch_row()[0];
$pending  = $db->query("SELECT COUNT(*) FROM reports WHERE status='Pending'")->fetch_row()[0];
$high     = $db->query("SELECT COUNT(*) FROM reports WHERE urgency='High'")->fetch_row()[0];
$resolved = $db->query("SELECT COUNT(*) FROM reports WHERE status='Resolved'")->fetch_row()[0];
$users    = $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$alerts   = $db->query("SELECT COUNT(*) FROM alerts")->fetch_row()[0];

// ── Cluster detection ─────────────────────────────────
$window   = date('Y-m-d H:i:s', strtotime("-" . ALERT_MINUTES . " minutes"));
$clusters = $db->prepare(
    "SELECT location, COUNT(*) as cnt
     FROM reports WHERE created_at >= ? AND status='Pending'
     GROUP BY location HAVING cnt >= 2
     ORDER BY cnt DESC"
);
$clusters->bind_param('s', $window);
$clusters->execute();
$clusterRows = $clusters->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Recent reports ────────────────────────────────────
$recent = $db->query(
    "SELECT r.*, u.name AS reporter, u.email AS rep_email
     FROM reports r JOIN users u ON u.id=r.user_id
     ORDER BY r.created_at DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// ── Reports by type (for mini chart) ─────────────────
$byType = $db->query(
    "SELECT type, COUNT(*) as cnt FROM reports GROUP BY type ORDER BY cnt DESC LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="main-layout">
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-label"><?= strtoupper($user['role']) ?></div>
    <a href="dashboard.php" class="sidebar-link active"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="reports.php"   class="sidebar-link"><span class="sidebar-icon">📋</span> All Reports</a>
    <a href="alerts.php"    class="sidebar-link"><span class="sidebar-icon">🚨</span> Send Alert</a>
    <?php if ($user['role']==='admin'): ?>
    <a href="users.php"     class="sidebar-link"><span class="sidebar-icon">👥</span> Users</a>
    <a href="logs.php"      class="sidebar-link"><span class="sidebar-icon">🧾</span> System Logs</a>
    <a href="admin.php"     class="sidebar-link"><span class="sidebar-icon">⚙️</span> Admin Panel</a>
    <?php endif; ?>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">System</div>
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>

  <!-- Mini stats in sidebar -->
  <div style="margin-top:auto;padding-top:20px;">
    <div class="sidebar-label">Quick Stats</div>
    <div style="font-family:var(--font-mono);font-size:0.75rem;padding:8px;background:var(--green-glow);border-radius:8px;margin:4px 0;">
      <div style="color:var(--text-muted);">Total Users</div>
      <div style="color:var(--green);font-size:1.2rem;"><?= $users ?></div>
    </div>
    <div style="font-family:var(--font-mono);font-size:0.75rem;padding:8px;background:rgba(255,59,59,.06);border-radius:8px;margin:4px 0;">
      <div style="color:var(--text-muted);">Alerts Sent</div>
      <div style="color:var(--red);font-size:1.2rem;"><?= $alerts ?></div>
    </div>
  </div>
</aside>

<!-- CONTENT -->
<main class="content">
  <div class="section-heading">
    <h1>Health Command Dashboard</h1>
    <p>Real-time overview of health reports across Kiambu County — <?= date('D, d M Y H:i') ?></p>
  </div>

  <!-- CLUSTER ALERT -->
  <?php if (!empty($clusterRows)): ?>
  <div class="alert-banner">
    <div class="alert-pulse"></div>
    <div style="flex:1">
      <strong style="color:var(--red);font-family:var(--font-display);">⚠ CLUSTER ALERT DETECTED</strong>
      <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;">
        Multiple reports received from the same area in the last <?= ALERT_MINUTES ?> minutes:
        <?php foreach($clusterRows as $c): ?>
          <strong style="color:var(--orange);"><?= $c['location'] ?> (<?= $c['cnt'] ?> reports)</strong>
        <?php endforeach; ?>
      </div>
    </div>
    <a href="alerts.php" class="btn btn-danger btn-sm">Send Alert →</a>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-val c-green"><?= $total ?></div>
      <div class="stat-label">Total Reports</div>
    </div>
    <div class="stat-card">
      <div class="stat-val c-orange"><?= $pending ?></div>
      <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card">
      <div class="stat-val c-red"><?= $high ?></div>
      <div class="stat-label">High Urgency</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $resolved ?></div>
      <div class="stat-label">Resolved</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Cluster monitor -->
    <div class="card">
      <div class="card-title">📍 Active Clusters (Last <?= ALERT_MINUTES ?> min)</div>
      <?php if (empty($clusterRows)): ?>
        <p style="color:var(--text-dim);font-size:0.85rem;margin-top:8px;">No clusters detected — all clear.</p>
      <?php else: ?>
        <?php foreach($clusterRows as $c): ?>
        <div class="cluster-item">
          <span class="loc"><?= htmlspecialchars($c['location']) ?></span>
          <span class="count"><?= $c['cnt'] ?> reports</span>
          <span class="badge badge-high">CLUSTER</span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Issues by type -->
    <div class="card">
      <div class="card-title">📊 Reports by Issue Type</div>
      <?php foreach($byType as $bt): ?>
      <?php $pct = $total ? round($bt['cnt']/$total*100) : 0; ?>
      <div style="margin-bottom:10px;">
        <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
          <span><?= htmlspecialchars($bt['type']) ?></span>
          <span style="color:var(--text-muted);font-family:var(--font-mono);"><?= $bt['cnt'] ?></span>
        </div>
        <div style="background:var(--dark4);border-radius:4px;height:5px;overflow:hidden;">
          <div style="width:<?= $pct ?>%;height:100%;background:var(--green);border-radius:4px;transition:width 1s;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Recent Reports -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🕐 Recent Reports</span>
      <a href="reports.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Type</th><th>Location</th><th>Urgency</th><th>Reporter</th><th>Status</th><th>Time</th></tr>
        </thead>
        <tbody>
          <?php foreach($recent as $r): ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-muted);">#<?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= htmlspecialchars($r['location']) ?></td>
            <td><span class="badge badge-<?= strtolower($r['urgency']) ?>"><?= $r['urgency'] ?></span></td>
            <td style="font-size:0.82rem;"><?= htmlspecialchars($r['reporter']) ?></td>
            <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
            <td style="font-size:0.75rem;color:var(--text-muted);font-family:var(--font-mono);"><?= date('d M H:i', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent)): ?>
          <tr><td colspan="7" style="color:var(--text-dim);text-align:center;padding:20px;">No reports yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>

<?php include 'includes/footer.php'; ?>
