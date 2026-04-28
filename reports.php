<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('officer','admin');
$pageTitle = 'All Reports';
$db        = db();

// ── Update report status / send feedback ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId  = (int)($_POST['report_id'] ?? 0);
    $action    = clean($_POST['action'] ?? '');
    $note      = clean($_POST['note']   ?? '');

    if ($reportId && $action === 'update_status') {
        $status = clean($_POST['status'] ?? 'Pending');
        $db->prepare("UPDATE reports SET status=?, officer_note=? WHERE id=?")
           ->execute_query([$status, $note, $reportId]);

        // Get reporter email to notify
        $rep = $db->prepare("SELECT u.email, u.name, r.type, r.location FROM reports r JOIN users u ON u.id=r.user_id WHERE r.id=?");
        $rep->bind_param('i', $reportId);
        $rep->execute();
        $repRow = $rep->get_result()->fetch_assoc();

        if ($repRow && $status === 'Resolved') {
            $body  = "Dear {$repRow['name']},\n\n";
            $body .= "Your health report #{$reportId} ({$repRow['type']} in {$repRow['location']}) ";
            $body .= "has been marked as RESOLVED by Kiambu County Health Officers.\n\n";
            if ($note) $body .= "Officer Note:\n$note\n\n";
            $body .= "Thank you for using CHRAS.\n" . SYSTEM_NAME;
            sendMail($repRow['email'], "[CHRAS] Your Report #$reportId Has Been Resolved", $body);
        }

        // Log
        $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
           ->execute_query([$user['id'], "Report status updated", "Report #$reportId → $status"]);

        $_SESSION['toast'] = ['msg'=>"Report #$reportId updated to $status."];
        header('Location: reports.php');
        exit;

    } elseif ($reportId && $action === 'send_feedback') {
        $db->prepare("INSERT INTO feedback (report_id,officer_id,message) VALUES (?,?,?)")
           ->execute_query([$reportId, $user['id'], $note]);

        // Notify resident
        $rep = $db->prepare("SELECT u.email,u.name FROM reports r JOIN users u ON u.id=r.user_id WHERE r.id=?");
        $rep->bind_param('i', $reportId);
        $rep->execute();
        $repRow = $rep->get_result()->fetch_assoc();
        if ($repRow) {
            $body  = "Dear {$repRow['name']},\n\n";
            $body .= "A health officer has sent you feedback on Report #{$reportId}:\n\n";
            $body .= "$note\n\n";
            $body .= "Track your reports: http://{$_SERVER['HTTP_HOST']}/chras/my_reports.php\n\n";
            $body .= SYSTEM_NAME;
            sendMail($repRow['email'], "[CHRAS] Officer Feedback on Report #{$reportId}", $body);
        }

        $_SESSION['toast'] = ['msg'=>"Feedback sent to resident for Report #$reportId."];
        header('Location: reports.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────
$filterStatus  = clean($_GET['status']   ?? '');
$filterUrgency = clean($_GET['urgency']  ?? '');
$filterLoc     = clean($_GET['location'] ?? '');
$search        = clean($_GET['q']        ?? '');

$where = '1=1';
$params = [];
$types  = '';

if ($filterStatus)  { $where .= ' AND r.status=?';  $params[] = $filterStatus;  $types .= 's'; }
if ($filterUrgency) { $where .= ' AND r.urgency=?'; $params[] = $filterUrgency; $types .= 's'; }
if ($filterLoc)     { $where .= ' AND r.location=?';$params[] = $filterLoc;     $types .= 's'; }
if ($search)        { $where .= ' AND (r.type LIKE ? OR r.description LIKE ? OR r.location LIKE ?)';
                      $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sss'; }

$sql  = "SELECT r.*, u.name AS reporter, u.email AS rep_email FROM reports r JOIN users u ON u.id=r.user_id WHERE $where ORDER BY r.created_at DESC";
$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$locations = ['Githunguri','Limuru','Kiambu Town','Thika','Ruiru','Juja','Kikuyu','Lari','Gatundu North','Gatundu South'];

include 'includes/header.php';
?>

<div class="main-layout">
<aside class="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-label"><?= strtoupper($user['role']) ?></div>
    <a href="dashboard.php" class="sidebar-link"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="reports.php"   class="sidebar-link active"><span class="sidebar-icon">📋</span> All Reports</a>
    <a href="alerts.php"    class="sidebar-link"><span class="sidebar-icon">🚨</span> Send Alert</a>
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
    <h1>All Reports</h1>
    <p><?= count($reports) ?> report(s) found — filter and manage below.</p>
  </div>

  <!-- Filters -->
  <div class="card">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="margin:0;flex:2;min-width:160px;">
        <label>Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Type, location, description...">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:120px;">
        <label>Status</label>
        <select name="status">
          <option value="">All</option>
          <option <?= $filterStatus==='Pending'  ?'selected':'' ?>>Pending</option>
          <option <?= $filterStatus==='Reviewed' ?'selected':'' ?>>Reviewed</option>
          <option <?= $filterStatus==='Resolved' ?'selected':'' ?>>Resolved</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:120px;">
        <label>Urgency</label>
        <select name="urgency">
          <option value="">All</option>
          <option <?= $filterUrgency==='High'   ?'selected':'' ?>>High</option>
          <option <?= $filterUrgency==='Medium' ?'selected':'' ?>>Medium</option>
          <option <?= $filterUrgency==='Low'    ?'selected':'' ?>>Low</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:120px;">
        <label>Location</label>
        <select name="location">
          <option value="">All</option>
          <?php foreach($locations as $l): ?>
          <option <?= $filterLoc===$l?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="reports.php" class="btn btn-ghost btn-sm">Reset</a>
    </form>
  </div>

  <!-- Reports Table -->
  <div class="card">
    <div class="card-title" style="margin-bottom:16px;">📋 Reports</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Type</th><th>Location</th><th>Urgency</th><th>Reporter</th><th>Status</th><th>Submitted</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach($reports as $r): ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-muted);">#<?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= htmlspecialchars($r['location']) ?></td>
            <td><span class="badge badge-<?= strtolower($r['urgency']) ?>"><?= $r['urgency'] ?></span></td>
            <td>
              <div><?= htmlspecialchars($r['reporter']) ?></div>
              <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($r['rep_email']) ?></div>
            </td>
            <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
            <td style="font-size:0.75rem;color:var(--text-muted);font-family:var(--font-mono);"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
              <button onclick="openModal(<?= htmlspecialchars(json_encode($r)) ?>)" class="btn btn-ghost btn-sm">Manage</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($reports)): ?>
          <tr><td colspan="8" style="color:var(--text-dim);text-align:center;padding:20px;">No reports match your filters.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<!-- MANAGE MODAL -->
<div id="modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
  <div style="background:var(--dark2);border:1px solid var(--border);border-radius:16px;width:500px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:28px;animation:fadeUp .3s ease;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <span class="card-title" id="modalTitle">Manage Report</span>
      <button onclick="closeModal()" style="background:none;border:none;color:var(--text-muted);font-size:1.4rem;cursor:pointer;">×</button>
    </div>

    <div id="modalDetail" style="font-size:0.84rem;color:var(--text-muted);margin-bottom:20px;line-height:1.8;background:var(--dark3);border-radius:8px;padding:14px;"></div>

    <form method="POST">
      <input type="hidden" name="report_id" id="modalReportId">

      <div class="form-group">
        <label>Update Status</label>
        <select name="status" id="modalStatus">
          <option>Pending</option>
          <option>Reviewed</option>
          <option>Resolved</option>
        </select>
      </div>
      <div class="form-group">
        <label>Officer Note / Feedback Message</label>
        <textarea name="note" rows="3" placeholder="Add a note or message to send to the resident..."></textarea>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
        <button type="submit" name="action" value="update_status" class="btn btn-primary btn-sm">Update Status</button>
        <button type="submit" name="action" value="send_feedback" class="btn btn-warning btn-sm">📧 Send Feedback Email</button>
        <button type="button" onclick="closeModal()" class="btn btn-ghost btn-sm">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(r) {
  document.getElementById('modalReportId').value = r.id;
  document.getElementById('modalTitle').textContent = 'Report #' + r.id + ' — ' + r.type;
  document.getElementById('modalStatus').value = r.status;
  document.getElementById('modalDetail').innerHTML =
    '<strong>Location:</strong> ' + r.location + '<br>' +
    '<strong>Urgency:</strong> ' + r.urgency + '<br>' +
    '<strong>Affected:</strong> ' + r.affected + '<br>' +
    '<strong>Reporter:</strong> ' + r.reporter + ' &lt;' + r.rep_email + '&gt;<br>' +
    '<strong>Description:</strong><br>' + r.description.replace(/\n/g,'<br>');
  document.getElementById('modal').style.display = 'flex';
}
function closeModal() { document.getElementById('modal').style.display = 'none'; }
</script>

<?php include 'includes/footer.php'; ?>
