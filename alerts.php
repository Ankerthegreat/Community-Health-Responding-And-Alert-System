<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('officer','admin');
$pageTitle = 'Send Alert';
$db        = db();
$success   = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $region    = clean($_POST['region']     ?? '');
    $alertType = clean($_POST['alert_type'] ?? '');
    $message   = clean($_POST['message']    ?? '');
    $sendTo    = clean($_POST['send_to']    ?? ADMIN_EMAIL);

    if (!$region || !$message) {
        $error = 'Region and message are required.';
    } else {
        // Save alert
        $db->prepare(
            "INSERT INTO alerts (sent_by,region,alert_type,message,sent_to) VALUES (?,?,?,?,?)"
        )->execute_query([$user['id'], $region, $alertType, $message, $sendTo]);

        // Send email
        $body  = "⚠ HEALTH ALERT — " . SYSTEM_NAME . "\n";
        $body .= str_repeat('─',40) . "\n";
        $body .= "Region     : $region\n";
        $body .= "Alert Type : $alertType\n";
        $body .= "Issued by  : {$user['name']} ({$user['role']})\n";
        $body .= "Date/Time  : " . date('D, d M Y H:i') . "\n\n";
        $body .= "MESSAGE:\n$message\n\n";
        $body .= str_repeat('─',40) . "\n";
        $body .= SYSTEM_NAME . " — Kiambu County Ministry of Health";

        $sent = sendMail($sendTo, "[CHRAS ALERT] $alertType — $region", $body);

        // Always send copy to admin
        if ($sendTo !== ADMIN_EMAIL) {
            sendMail(ADMIN_EMAIL, "[CHRAS ALERT] $alertType — $region", $body);
        }

        $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
           ->execute_query([$user['id'], 'Alert sent', "Region: $region, Type: $alertType, To: $sendTo"]);

        $success = "Alert sent successfully to $sendTo" . ($sendTo !== ADMIN_EMAIL ? " and admin." : ".");
    }
}

// Past alerts
$pastAlerts = $db->query(
    "SELECT a.*, u.name AS sender FROM alerts a JOIN users u ON u.id=a.sent_by ORDER BY a.created_at DESC LIMIT 20"
)->fetch_all(MYSQLI_ASSOC);

$locations = ['All of Kiambu County','Githunguri','Limuru','Kiambu Town','Thika','Ruiru','Juja','Kikuyu','Lari'];

include 'includes/header.php';
?>

<div class="main-layout">
<aside class="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-label"><?= strtoupper($user['role']) ?></div>
    <a href="dashboard.php" class="sidebar-link"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="reports.php"   class="sidebar-link"><span class="sidebar-icon">📋</span> All Reports</a>
    <a href="alerts.php"    class="sidebar-link active"><span class="sidebar-icon">🚨</span> Send Alert</a>
    <?php if ($user['role']==='admin'): ?>
    <a href="users.php"  class="sidebar-link"><span class="sidebar-icon">👥</span> Users</a>
    <a href="logs.php"   class="sidebar-link"><span class="sidebar-icon">🧾</span> Logs</a>
    <a href="admin.php"  class="sidebar-link"><span class="sidebar-icon">⚙️</span> Admin</a>
    <?php endif; ?>
  </div>
  <div class="sidebar-section">
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>
</aside>

<main class="content">
  <div class="section-heading">
    <h1>Send Health Alert</h1>
    <p>Issue an emergency health alert to residents or the admin email. All alerts are logged.</p>
  </div>

  <?php if ($error):   ?><div class="info-box" style="border-color:var(--red);color:var(--red);margin-bottom:20px;">⚠ <?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="info-box" style="margin-bottom:20px;">✅ <?= $success ?></div><?php endif; ?>

  <div class="card">
    <div class="card-title" style="margin-bottom:18px;">🚨 New Alert</div>
    <form method="POST">
      <div class="form-grid">
        <div class="form-group">
          <label>Target Region *</label>
          <select name="region" required>
            <?php foreach($locations as $l): ?>
            <option <?= ($_POST['region']??'')===$l?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Alert Type</label>
          <select name="alert_type">
            <option>Disease Outbreak Warning</option>
            <option>Sanitation Advisory</option>
            <option>Water Safety Alert</option>
            <option>General Health Notice</option>
            <option>Vaccination Campaign</option>
            <option>Emergency Response</option>
          </select>
        </div>
        <div class="form-group">
          <label>Send To (Email)</label>
          <input type="email" name="send_to" value="<?= ADMIN_EMAIL ?>" placeholder="recipient@email.com">
        </div>
        <div class="form-group full">
          <label>Alert Message *</label>
          <textarea name="message" rows="5" placeholder="Write a clear, actionable alert message. Example:&#10;Warning: Diarrhea cases reported in Githunguri. Residents are advised to boil drinking water and seek medical attention if symptoms develop." required><?= htmlspecialchars($_POST['message']??'') ?></textarea>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin-top:18px;align-items:center;">
        <button type="submit" class="btn btn-danger">🚨 Send Alert Now</button>
        <span style="font-size:0.78rem;color:var(--text-muted);">Alert will be emailed immediately + copied to admin.</span>
      </div>
    </form>
  </div>

  <!-- Past Alerts -->
  <div class="card">
    <div class="card-title" style="margin-bottom:16px;">📜 Alert History</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Region</th><th>Type</th><th>Sent By</th><th>Sent To</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach($pastAlerts as $a): ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-muted);">#<?= $a['id'] ?></td>
            <td><?= htmlspecialchars($a['region']) ?></td>
            <td><span class="badge badge-high"><?= htmlspecialchars($a['alert_type']) ?></span></td>
            <td><?= htmlspecialchars($a['sender']) ?></td>
            <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($a['sent_to']) ?></td>
            <td style="font-size:0.75rem;color:var(--text-muted);font-family:var(--font-mono);"><?= date('d M Y H:i', strtotime($a['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($pastAlerts)): ?>
          <tr><td colspan="6" style="color:var(--text-dim);text-align:center;padding:20px;">No alerts sent yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
