<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('admin');
$pageTitle = 'System Logs';
$db        = db();

// Optional: clear all logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    $db->query("DELETE FROM system_logs");
    $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
       ->execute_query([$user['id'], 'Logs cleared', 'Admin cleared all system logs.']);
    $_SESSION['toast'] = ['msg' => 'All logs cleared.'];
    header('Location: logs.php');
    exit;
}

// Filters
$filterAction = clean($_GET['action_filter'] ?? '');
$search       = clean($_GET['q'] ?? '');
$limit        = max(10, min(200, (int)($_GET['limit'] ?? 50)));

$where  = '1=1';
$params = [];
$types  = '';

if ($filterAction) {
    $where   .= ' AND l.action LIKE ?';
    $params[] = "%$filterAction%";
    $types   .= 's';
}
if ($search) {
    $where   .= ' AND (l.action LIKE ? OR l.detail LIKE ? OR u.name LIKE ?)';
    $s        = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'sss';
}

$sql  = "SELECT l.*, u.name AS user_name, u.role AS user_role
         FROM system_logs l
         LEFT JOIN users u ON u.id = l.user_id
         WHERE $where
         ORDER BY l.created_at DESC
         LIMIT $limit";
$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalLogs = $db->query("SELECT COUNT(*) FROM system_logs")->fetch_row()[0];

// Unique actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM system_logs ORDER BY action")
              ->fetch_all(MYSQLI_ASSOC);

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
    <a href="logs.php"      class="sidebar-link active"><span class="sidebar-icon">🧾</span> Logs</a>
    <a href="admin.php"     class="sidebar-link"><span class="sidebar-icon">⚙️</span> Admin</a>
  </div>
  <div class="sidebar-section">
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>
</aside>

<main class="content">
  <div class="section-heading">
    <h1>System Logs</h1>
    <p><?= $totalLogs ?> total log entries — showing <?= count($logs) ?>.</p>
  </div>

  <!-- Filter bar -->
  <div class="card">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="margin:0;flex:2;min-width:160px;">
        <label>Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="User name, action, detail...">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:140px;">
        <label>Filter by Action</label>
        <select name="action_filter">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
          <option value="<?= htmlspecialchars($a['action']) ?>"
            <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['action']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:80px;">
        <label>Show</label>
        <select name="limit">
          <option value="25"  <?= $limit===25  ?'selected':'' ?>>25</option>
          <option value="50"  <?= $limit===50  ?'selected':'' ?>>50</option>
          <option value="100" <?= $limit===100 ?'selected':'' ?>>100</option>
          <option value="200" <?= $limit===200 ?'selected':'' ?>>200</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="logs.php" class="btn btn-ghost btn-sm">Reset</a>
    </form>
  </div>

  <!-- Logs table -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🧾 Activity Log</span>
      <form method="POST" onsubmit="return confirm('Clear ALL system logs? This cannot be undone.');">
        <input type="hidden" name="action" value="clear_logs">
        <button type="submit" class="btn btn-danger btn-sm">🗑 Clear All Logs</button>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Role</th>
            <th>Action</th>
            <th>Detail</th>
            <th>IP</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-dim);"><?= $log['id'] ?></td>
            <td style="font-size:0.82rem;">
              <?= $log['user_name'] ? htmlspecialchars($log['user_name']) : '<span style="color:var(--text-dim)">System</span>' ?>
            </td>
            <td>
              <?php if ($log['user_role']): ?>
              <span class="badge badge-<?= $log['user_role'] === 'admin' ? 'high' : ($log['user_role'] === 'officer' ? 'reviewed' : 'resolved') ?>">
                <?= strtoupper($log['user_role']) ?>
              </span>
              <?php endif; ?>
            </td>
            <td style="font-family:var(--font-mono);font-size:0.78rem;color:var(--green);">
              <?= htmlspecialchars($log['action']) ?>
            </td>
            <td style="font-size:0.8rem;color:var(--text-muted);max-width:300px;">
              <?= htmlspecialchars($log['detail']) ?>
            </td>
            <td style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-dim);">
              <?= htmlspecialchars($log['ip'] ?? '—') ?>
            </td>
            <td style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-muted);white-space:nowrap;">
              <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
          <tr>
            <td colspan="7" style="color:var(--text-dim);text-align:center;padding:24px;">
              No log entries match your filters.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
