<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('admin');
$pageTitle = 'Admin Panel';
$db        = db();
$success   = $error = '';

// ── Handle password change ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $oldPw  = $_POST['old_password'] ?? '';
    $newPw  = $_POST['new_password'] ?? '';
    $confPw = $_POST['confirm_password'] ?? '';

    $row = $db->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $row->bind_param('i', $user['id']);
    $row->execute();
    $current = $row->get_result()->fetch_assoc();

    if (!password_verify($oldPw, $current['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPw) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPw !== $confPw) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPw, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute_query([$hash, $user['id']]);
        $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
           ->execute_query([$user['id'], 'Password changed', 'Admin changed their password.']);
        $success = 'Password updated successfully.';
    }
}

// ── Handle test email ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_email') {
    $testTo   = clean($_POST['test_email'] ?? ADMIN_EMAIL);
    $testBody = "This is a test email from CHRAS.\n\n";
    $testBody .= "System: " . SYSTEM_NAME . "\n";
    $testBody .= "Sent at: " . date('Y-m-d H:i:s') . "\n";
    $testBody .= "Sent by: {$user['name']} ({$user['email']})\n\n";
    $testBody .= "If you received this, PHP mail() is working correctly.";

    $sent = sendMail($testTo, '[CHRAS] Test Email — ' . date('H:i:s'), $testBody);
    $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
       ->execute_query([$user['id'], 'Test email sent', "To: $testTo"]);
    $success = $sent
        ? "Test email sent to $testTo. Check your inbox."
        : "sendMail() returned false. Check your server's mail config (see notes below).";
}

// ── Aggregate stats ──────────────────────────────────
$stats = [
    'users'    => $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
    'reports'  => $db->query("SELECT COUNT(*) FROM reports")->fetch_row()[0],
    'pending'  => $db->query("SELECT COUNT(*) FROM reports WHERE status='Pending'")->fetch_row()[0],
    'resolved' => $db->query("SELECT COUNT(*) FROM reports WHERE status='Resolved'")->fetch_row()[0],
    'alerts'   => $db->query("SELECT COUNT(*) FROM alerts")->fetch_row()[0],
    'logs'     => $db->query("SELECT COUNT(*) FROM system_logs")->fetch_row()[0],
    'officers' => $db->query("SELECT COUNT(*) FROM users WHERE role='officer'")->fetch_row()[0],
    'high'     => $db->query("SELECT COUNT(*) FROM reports WHERE urgency='High' AND status='Pending'")->fetch_row()[0],
];

// ── Reports by location ──────────────────────────────
$byLoc = $db->query(
    "SELECT location, COUNT(*) as cnt FROM reports GROUP BY location ORDER BY cnt DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// ── Reports by urgency ───────────────────────────────
$byUrg = $db->query(
    "SELECT urgency, COUNT(*) as cnt FROM reports GROUP BY urgency"
)->fetch_all(MYSQLI_ASSOC);

// ── Daily report trend (last 7 days) ─────────────────
$trend = $db->query(
    "SELECT DATE(created_at) AS day, COUNT(*) as cnt
     FROM reports
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY day ORDER BY day ASC"
)->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="main-layout">
<aside class="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-label">ADMIN</div>
    <a href="dashboard.php" class="sidebar-link"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="reports.php"   class="sidebar-link"><span class="sidebar-icon">📋</span> All Reports</a>
    <a href="alerts.php"    class="sidebar-link"><span class="sidebar-icon">🚨</span> Alerts</a>
    <a href="users.php"     class="sidebar-link"><span class="sidebar-icon">👥</span> Users</a>
    <a href="logs.php"      class="sidebar-link"><span class="sidebar-icon">🧾</span> Logs</a>
    <a href="admin.php"     class="sidebar-link active"><span class="sidebar-icon">⚙️</span> Admin</a>
  </div>
  <div class="sidebar-section">
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>
</aside>

<main class="content">
  <div class="section-heading">
    <h1>Admin Control Panel</h1>
    <p>System overview, email configuration, and account settings.</p>
  </div>

  <?php if ($error):   ?><div class="info-box" style="border-color:var(--red);color:var(--red);margin-bottom:20px;">⚠ <?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="info-box" style="margin-bottom:20px;">✅ <?= $success ?></div><?php endif; ?>

  <!-- Full stats -->
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card"><div class="stat-val c-green"><?= $stats['users'] ?></div><div class="stat-label">Total Users</div></div>
    <div class="stat-card"><div class="stat-val c-blue"><?= $stats['officers'] ?></div><div class="stat-label">Health Officers</div></div>
    <div class="stat-card"><div class="stat-val"><?= $stats['reports'] ?></div><div class="stat-label">Total Reports</div></div>
    <div class="stat-card"><div class="stat-val c-orange"><?= $stats['pending'] ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-val c-red"><?= $stats['high'] ?></div><div class="stat-label">High Urgency Pending</div></div>
    <div class="stat-card"><div class="stat-val c-green"><?= $stats['resolved'] ?></div><div class="stat-label">Resolved</div></div>
    <div class="stat-card"><div class="stat-val c-red"><?= $stats['alerts'] ?></div><div class="stat-label">Alerts Sent</div></div>
    <div class="stat-card"><div class="stat-val"><?= $stats['logs'] ?></div><div class="stat-label">Log Entries</div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Reports by Location -->
    <div class="card">
      <div class="card-title" style="margin-bottom:16px;">📍 Reports by Location</div>
      <?php if (empty($byLoc)): ?>
        <p style="color:var(--text-dim);font-size:0.85rem;">No data yet.</p>
      <?php else: ?>
        <?php $maxL = max(array_column($byLoc,'cnt')); ?>
        <?php foreach ($byLoc as $b): ?>
        <div style="margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
            <span><?= htmlspecialchars($b['location']) ?></span>
            <span style="font-family:var(--font-mono);color:var(--text-muted);"><?= $b['cnt'] ?></span>
          </div>
          <div style="background:var(--dark4);border-radius:4px;height:6px;">
            <div style="width:<?= round($b['cnt']/$maxL*100) ?>%;height:100%;background:linear-gradient(90deg,var(--green-dim),var(--green));border-radius:4px;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Reports by Urgency + 7-day trend -->
    <div class="card">
      <div class="card-title" style="margin-bottom:16px;">⚡ Reports by Urgency</div>
      <?php
      $urgColors = ['High'=>'var(--red)','Medium'=>'var(--orange)','Low'=>'var(--green)'];
      foreach ($byUrg as $u):
        $pct = $stats['reports'] ? round($u['cnt']/$stats['reports']*100) : 0;
      ?>
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
          <span style="color:<?= $urgColors[$u['urgency']] ?? 'var(--text)' ?>;"><?= $u['urgency'] ?></span>
          <span style="font-family:var(--font-mono);color:var(--text-muted);"><?= $u['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <div style="background:var(--dark4);border-radius:4px;height:8px;">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $urgColors[$u['urgency']] ?? 'var(--green)' ?>;border-radius:4px;opacity:0.8;"></div>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="card-title" style="margin-top:20px;margin-bottom:12px;">📅 7-Day Report Trend</div>
      <?php if (empty($trend)): ?>
        <p style="color:var(--text-dim);font-size:0.82rem;">No reports in the last 7 days.</p>
      <?php else: ?>
        <?php $maxT = max(array_column($trend,'cnt')); ?>
        <div style="display:flex;align-items:flex-end;gap:6px;height:60px;">
          <?php foreach ($trend as $t): ?>
          <?php $h = $maxT ? round($t['cnt']/$maxT*56)+4 : 4; ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
            <div style="font-family:var(--font-mono);font-size:0.6rem;color:var(--text-muted);"><?= $t['cnt'] ?></div>
            <div style="width:100%;height:<?= $h ?>px;background:var(--green);border-radius:3px 3px 0 0;opacity:0.7;"></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:6px;margin-top:4px;">
          <?php foreach ($trend as $t): ?>
          <div style="flex:1;text-align:center;font-family:var(--font-mono);font-size:0.55rem;color:var(--text-dim);">
            <?= date('d/m', strtotime($t['day'])) ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Email Test -->
    <div class="card">
      <div class="card-title" style="margin-bottom:16px;">📧 Test Email System</div>
      <p style="font-size:0.83rem;color:var(--text-muted);margin-bottom:16px;line-height:1.6;">
        Send a test email to verify PHP <code style="color:var(--green);background:var(--dark3);padding:1px 5px;border-radius:3px;">mail()</code> is working on your server.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="test_email">
        <div class="form-group">
          <label>Send Test To</label>
          <input type="email" name="test_email" value="<?= ADMIN_EMAIL ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Send Test Email</button>
      </form>

      <div class="info-box" style="margin-top:16px;">
        <strong>⚠ Email Not Working?</strong><br>
        PHP <code>mail()</code> requires a configured MTA (Postfix/Sendmail) on the server.
        On <strong>localhost/XAMPP</strong>, use <strong>SendGrid</strong> or <strong>Mailtrap</strong> instead:<br><br>
        1. Install <code>composer require phpmailer/phpmailer</code><br>
        2. Replace <code>sendMail()</code> in <code>includes/config.php</code> with PHPMailer SMTP.<br>
        3. Set SMTP host, port, username, password in config.<br><br>
        On a <strong>live server</strong> (cPanel/VPS), <code>mail()</code> usually works out of the box.
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-title" style="margin-bottom:16px;">🔑 Change Password</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="old_password" placeholder="Current password" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Min. 6 characters" required>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" placeholder="Repeat new password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Update Password</button>
      </form>
    </div>

  </div>

  <!-- System info -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px;">🖥 System Information</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;font-size:0.82rem;">
      <div style="background:var(--dark3);border-radius:8px;padding:12px;">
        <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:0.68rem;margin-bottom:4px;">PHP VERSION</div>
        <div style="color:var(--green);"><?= phpversion() ?></div>
      </div>
      <div style="background:var(--dark3);border-radius:8px;padding:12px;">
        <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:0.68rem;margin-bottom:4px;">MYSQL VERSION</div>
        <div style="color:var(--green);"><?= $db->server_info ?></div>
      </div>
      <div style="background:var(--dark3);border-radius:8px;padding:12px;">
        <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:0.68rem;margin-bottom:4px;">SERVER TIME</div>
        <div style="color:var(--green);"><?= date('Y-m-d H:i:s') ?></div>
      </div>
      <div style="background:var(--dark3);border-radius:8px;padding:12px;">
        <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:0.68rem;margin-bottom:4px;">ADMIN EMAIL</div>
        <div style="color:var(--green);"><?= ADMIN_EMAIL ?></div>
      </div>
      <div style="background:var(--dark3);border-radius:8px;padding:12px;">
        <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:0.68rem;margin-bottom:4px;">ALERT THRESHOLD</div>
        <div style="color:var(--orange);"><?= ALERT_COUNT ?> reports / <?= ALERT_MINUTES ?> min</div>
      </div>
      <div style="background:var(--dark3);border-radius:8px;padding:12px;">
        <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:0.68rem;margin-bottom:4px;">SESSION USER</div>
        <div style="color:var(--green);"><?= htmlspecialchars($user['name']) ?> [<?= $user['role'] ?>]</div>
      </div>
    </div>
  </div>

  <!-- Deployment checklist -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px;">🚀 Deployment Checklist</div>
    <div style="font-size:0.83rem;line-height:2.2;color:var(--text-muted);">
      <?php
      $checks = [
        ['Run install.sql to create the database',    true],
        ['Set DB_USER and DB_PASS in config.php',     DB_PASS !== '' || DB_USER !== 'root'],
        ['Change default admin password',             true],
        ['Configure server mail (Postfix/SendGrid)',   function_exists('mail')],
        ['Enable HTTPS on production server',         isset($_SERVER['HTTPS'])],
        ['Set correct SYSTEM_FROM email in config',   SYSTEM_FROM !== 'noreply@chras.kiambu.go.ke'],
        ['Back up database regularly',                true],
      ];
      foreach ($checks as [$label, $done]):
      ?>
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="color:<?= $done?'var(--green)':'var(--orange)' ?>;">
          <?= $done ? '✅' : '⚠️' ?>
        </span>
        <?= $label ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</main>
</div>

<?php include 'includes/footer.php'; ?>
