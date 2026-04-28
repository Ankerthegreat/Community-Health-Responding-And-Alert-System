<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('resident','officer','admin');
$pageTitle = 'My Reports';
$db        = db();

$reports = $db->prepare(
    "SELECT r.*, f.message AS feedback_msg, f.created_at AS feedback_time
     FROM reports r
     LEFT JOIN feedback f ON f.report_id = r.id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC"
);
$reports->bind_param('i', $user['id']);
$reports->execute();
$rows = $reports->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="main-layout">
<aside class="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-label">Resident</div>
    <a href="report.php"     class="sidebar-link"><span class="sidebar-icon">📋</span> Submit Report</a>
    <a href="my_reports.php" class="sidebar-link active"><span class="sidebar-icon">📁</span> My Reports</a>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">System</div>
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>
</aside>

<main class="content">
  <div class="section-heading">
    <h1>My Reports</h1>
    <p>Track all health reports you have submitted to CHRAS.</p>
  </div>

  <?php if (empty($rows)): ?>
  <div class="info-box">
    You haven't submitted any reports yet. <a href="report.php" style="color:var(--green)">Submit your first report →</a>
  </div>
  <?php else: ?>

  <div class="card">
    <div class="card-title" style="margin-bottom:16px;">📁 Submitted Reports (<?= count($rows) ?>)</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#ID</th><th>Type</th><th>Location</th><th>Urgency</th>
            <th>Affected</th><th>Status</th><th>Submitted</th><th>Feedback</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-muted);">#<?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= htmlspecialchars($r['location']) ?></td>
            <td><span class="badge badge-<?= strtolower($r['urgency']) ?>"><?= $r['urgency'] ?></span></td>
            <td><?= $r['affected'] ?></td>
            <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
            <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
            <td style="font-size:0.8rem;color:var(--text-muted);">
              <?php if ($r['feedback_msg']): ?>
                <span style="color:var(--green);">✅</span> <?= htmlspecialchars(substr($r['feedback_msg'],0,60)) ?>…
              <?php elseif ($r['officer_note']): ?>
                <span style="color:var(--blue);">📝</span> <?= htmlspecialchars(substr($r['officer_note'],0,60)) ?>…
              <?php else: ?>
                <span style="color:var(--text-dim);">Awaiting review</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>
</main>
</div>

<?php include 'includes/footer.php'; ?>
