<?php
session_start();
require_once 'includes/config.php';
$user     = requireRole('resident', 'officer', 'admin');
$pageTitle = 'Submit Report';
$db        = db();
$success   = $error = '';

// ── LOCATIONS ───────────────────────────────────────
$locations = ['Githunguri','Limuru','Kiambu Town','Thika','Ruiru','Juja',
              'Kikuyu','Lari','Gatundu North','Gatundu South','Karuri','Kabete'];
$types     = ['Fever / Flu Outbreak','Diarrhea / Cholera','Malaria',
              'Sanitation Hazard','Water Contamination','Animal Disease Risk',
              'Respiratory Illness','Typhoid','Other'];

// ── POST HANDLER ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type     = clean($_POST['type']        ?? '');
    $location = clean($_POST['location']    ?? '');
    $urgency  = clean($_POST['urgency']     ?? 'Medium');
    $desc     = clean($_POST['description'] ?? '');
    $affected = (int)($_POST['affected']    ?? 0);

    if (!$type || !$location || !$desc) {
        $error = 'Please fill in Type, Location, and Description.';
    } else {
        // ── Save to DB ──────────────────────────────────
        $stmt = $db->prepare(
            "INSERT INTO reports (user_id,type,description,location,urgency,affected)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('issssi', $user['id'], $type, $desc, $location, $urgency, $affected);
        $stmt->execute();
        $reportId = $db->insert_id;

        // ── Log ─────────────────────────────────────────
        $logMsg = "Report #$reportId submitted: $type in $location [$urgency]";
        $ip     = $_SERVER['REMOTE_ADDR'];
        $db->prepare("INSERT INTO system_logs (user_id,action,detail,ip) VALUES (?,?,?,?)")
           ->execute_query([$user['id'], 'Report submitted', $logMsg, $ip]);

        // ── Email admin ──────────────────────────────────
        $adminBody = "NEW HEALTH REPORT — CHRAS\n";
        $adminBody .= str_repeat('─', 40) . "\n";
        $adminBody .= "Report ID  : #$reportId\n";
        $adminBody .= "Time       : " . date('Y-m-d H:i:s') . "\n";
        $adminBody .= "Type       : $type\n";
        $adminBody .= "Location   : $location\n";
        $adminBody .= "Urgency    : $urgency\n";
        $adminBody .= "Affected   : $affected person(s)\n";
        $adminBody .= "Reporter   : {$user['name']} ({$user['email']})\n\n";
        $adminBody .= "Description:\n$desc\n\n";
        $adminBody .= str_repeat('─', 40) . "\n";
        $adminBody .= "Login to the CHRAS dashboard to review.\n";
        $adminBody .= SYSTEM_NAME;

        sendMail(ADMIN_EMAIL, "[CHRAS] New $urgency Report — $location (#{$reportId})", $adminBody);

        // ── Email resident confirmation ────────────────
        $confBody  = "Dear {$user['name']},\n\n";
        $confBody .= "Your health report has been received by CHRAS — Kiambu County.\n\n";
        $confBody .= "Report Details:\n";
        $confBody .= "• Reference  : #$reportId\n";
        $confBody .= "• Type       : $type\n";
        $confBody .= "• Location   : $location\n";
        $confBody .= "• Urgency    : $urgency\n";
        $confBody .= "• Submitted  : " . date('D, d M Y H:i') . "\n\n";
        $confBody .= "Health officers will review your report and respond shortly.\n";
        $confBody .= "You can track status at: http://{$_SERVER['HTTP_HOST']}/chras/my_reports.php\n\n";
        $confBody .= "Stay safe,\n" . SYSTEM_NAME;

        sendMail($user['email'], "[CHRAS] Report Received — Reference #$reportId", $confBody);

        // ── Check cluster / auto-alert ──────────────────
        $window = date('Y-m-d H:i:s', strtotime("-" . ALERT_MINUTES . " minutes"));
        $cq     = $db->prepare(
            "SELECT COUNT(*) as cnt FROM reports
             WHERE location=? AND created_at >= ? AND status='Pending'"
        );
        $cq->bind_param('ss', $location, $window);
        $cq->execute();
        $cnt = $cq->get_result()->fetch_assoc()['cnt'];

        if ($cnt >= ALERT_COUNT) {
            // Auto-alert the admin
            $alertMsg  = "⚠ AUTO-ALERT: $cnt reports received from $location in the last " . ALERT_MINUTES . " minutes.\n";
            $alertMsg .= "Latest: $type — $urgency urgency.\n";
            $alertMsg .= "Possible outbreak — immediate review required.\n\n";
            $alertMsg .= "Login: http://{$_SERVER['HTTP_HOST']}/chras/dashboard.php\n" . SYSTEM_NAME;
            sendMail(ADMIN_EMAIL, "[CHRAS] 🚨 CLUSTER ALERT — $location", $alertMsg);

            // Save to alerts table
            $db->prepare(
                "INSERT INTO alerts (report_id,sent_by,region,alert_type,message,sent_to)
                 VALUES (?,?,?,?,?,?)"
            )->execute_query([$reportId, $user['id'], $location,
                'Auto-Cluster Alert', $alertMsg, ADMIN_EMAIL]);
        }

        $success = "Report #$reportId submitted! Confirmation sent to {$user['email']}.";
    }
}

include 'includes/header.php';
?>

<div class="main-layout">
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-label">Resident</div>
    <a href="report.php"     class="sidebar-link active"><span class="sidebar-icon">📋</span> Submit Report</a>
    <a href="my_reports.php" class="sidebar-link"><span class="sidebar-icon">📁</span> My Reports</a>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">System</div>
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>
</aside>

<!-- CONTENT -->
<main class="content">
  <div class="section-heading">
    <h1>Submit Health Report</h1>
    <p>Report a health concern in your area. Officers are notified instantly.</p>
  </div>

  <?php if ($error):   ?><div class="info-box" style="border-color:var(--red);color:var(--red);margin-bottom:20px;">⚠ <?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="info-box" style="margin-bottom:20px;">✅ <?= $success ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header">
      <span class="card-title">📋 New Report</span>
      <span style="font-size:0.75rem;color:var(--text-muted);font-family:var(--font-mono);"><?= date('D, d M Y') ?></span>
    </div>

    <form method="POST">
      <div class="form-grid">

        <div class="form-group">
          <label>Issue Type *</label>
          <select name="type" required>
            <option value="">Select type...</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= $t ?>" <?= ($_POST['type']??'')===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Location (Ward/Village) *</label>
          <select name="location" required>
            <option value="">Select location...</option>
            <?php foreach ($locations as $l): ?>
            <option value="<?= $l ?>" <?= ($_POST['location']??'')===$l?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Urgency Level</label>
          <select name="urgency">
            <option value="Low"    <?= ($_POST['urgency']??'')==='Low'   ?'selected':'' ?>>🟢 Low</option>
            <option value="Medium" <?= ($_POST['urgency']??'Medium')==='Medium'?'selected':'' ?>>🟡 Medium</option>
            <option value="High"   <?= ($_POST['urgency']??'')==='High'  ?'selected':'' ?>>🔴 High — Immediate attention needed</option>
          </select>
        </div>

        <div class="form-group">
          <label>Estimated People Affected</label>
          <input type="number" name="affected" min="0" value="<?= (int)($_POST['affected']??0) ?>" placeholder="e.g. 5">
        </div>

        <div class="form-group full">
          <label>Description *</label>
          <textarea name="description" rows="5" placeholder="Describe the health issue in detail — symptoms observed, how long it has been happening, any actions taken so far..." required><?= htmlspecialchars($_POST['description']??'') ?></textarea>
        </div>

      </div>

      <div style="display:flex;align-items:center;gap:14px;margin-top:20px;">
        <button type="submit" class="btn btn-primary">
          📧 Submit &amp; Notify Officers
        </button>
        <span style="font-size:0.78rem;color:var(--text-muted);">
          A confirmation email will be sent to <strong><?= htmlspecialchars($user['email']) ?></strong>
        </span>
      </div>
    </form>
  </div>

  <!-- Info box -->
  <div class="info-box">
    <strong>How it works:</strong> Your report is sent immediately to Kiambu County health officers.
    If <strong><?= ALERT_COUNT ?>+ reports</strong> come from the same area within
    <strong><?= ALERT_MINUTES ?> minutes</strong>, an automatic cluster alert is triggered.
    You can track the status of your reports in <a href="my_reports.php" style="color:var(--green)">My Reports</a>.
  </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
