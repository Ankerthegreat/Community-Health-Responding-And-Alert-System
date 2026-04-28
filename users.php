<?php
session_start();
require_once 'includes/config.php';
$user      = requireRole('admin');
$pageTitle = 'Manage Users';
$db        = db();

// Delete user
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    $uid = (int)$_POST['uid'];
    if ($uid !== $user['id']) {
        $db->prepare("DELETE FROM users WHERE id=?")->execute_query([$uid]);
        $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
           ->execute_query([$user['id'],'User deleted',"User ID #$uid"]);
        $_SESSION['toast'] = ['msg'=>"User #$uid deleted."];
        header('Location: users.php'); exit;
    }
}
// Update role
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='change_role') {
    $uid  = (int)$_POST['uid'];
    $role = clean($_POST['role']);
    if (in_array($role,['resident','officer','admin'])) {
        $db->prepare("UPDATE users SET role=? WHERE id=?")->execute_query([$role,$uid]);
        $db->prepare("INSERT INTO system_logs (user_id,action,detail) VALUES (?,?,?)")
           ->execute_query([$user['id'],'Role changed',"User #$uid → $role"]);
        $_SESSION['toast'] = ['msg'=>"User #$uid role changed to $role."];
        header('Location: users.php'); exit;
    }
}

$users = $db->query(
    "SELECT u.*, (SELECT COUNT(*) FROM reports r WHERE r.user_id=u.id) AS report_cnt
     FROM users u ORDER BY u.created_at DESC"
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
    <a href="users.php"     class="sidebar-link active"><span class="sidebar-icon">👥</span> Users</a>
    <a href="logs.php"      class="sidebar-link"><span class="sidebar-icon">🧾</span> Logs</a>
    <a href="admin.php"     class="sidebar-link"><span class="sidebar-icon">⚙️</span> Admin</a>
  </div>
  <div class="sidebar-section">
    <a href="logout.php" class="sidebar-link"><span class="sidebar-icon">🚪</span> Logout</a>
  </div>
</aside>

<main class="content">
  <div class="section-heading">
    <h1>User Management</h1>
    <p><?= count($users) ?> registered users in the system.</p>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:16px;">👥 All Users</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Reports</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-muted);">#<?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td style="font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                <select name="role" onchange="this.form.submit()" style="padding:4px 8px;font-size:0.75rem;">
                  <option <?= $u['role']==='resident'?'selected':'' ?>>resident</option>
                  <option <?= $u['role']==='officer' ?'selected':'' ?>>officer</option>
                  <option <?= $u['role']==='admin'   ?'selected':'' ?>>admin</option>
                </select>
              </form>
            </td>
            <td style="font-family:var(--font-mono);color:var(--green);"><?= $u['report_cnt'] ?></td>
            <td style="font-size:0.75rem;color:var(--text-muted);font-family:var(--font-mono);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <?php if ($u['id'] !== $user['id']): ?>
              <form method="POST" onsubmit="return confirm('Delete user #<?= $u['id'] ?>?');" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
              <?php else: ?>
              <span style="color:var(--text-dim);font-size:0.75rem;">(you)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>
