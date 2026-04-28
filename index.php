<?php
session_start();
require_once 'includes/config.php';

// Already logged in — redirect
if (currentUser()) {
    $role = $_SESSION['user']['role'];
    header('Location: ' . ($role === 'resident' ? 'report.php' : 'dashboard.php'));
    exit;
}

$error   = '';
$success = '';
$mode    = $_POST['mode'] ?? 'login';   // 'login' | 'register'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name     = clean($_POST['name'] ?? '');
    $role     = clean($_POST['role'] ?? 'resident');
    $db       = db();

    if ($mode === 'login') {
        // ── LOGIN ────────────────────────────────────────
        $stmt = $db->prepare("SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user'] = ['id'=>$row['id'],'name'=>$row['name'],'email'=>$row['email'],'role'=>$row['role']];

            // Log it
            $uid = $row['id']; $ip = $_SERVER['REMOTE_ADDR'];
            $log = "User logged in";
            $db->prepare("INSERT INTO system_logs (user_id,action,ip) VALUES (?,?,?)")
               ->execute_query([$uid, $log, $ip]);

            $redirect = $row['role'] === 'resident' ? 'report.php' : 'dashboard.php';
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }

    } elseif ($mode === 'register') {
        // ── REGISTER ─────────────────────────────────────
        if (!$name || !$email || !$password) {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!in_array($role, ['resident','officer','admin'])) {
            $error = 'Invalid role selected.';
        } else {
            // Check duplicate
            $check = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $check->bind_param('s', $email);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins  = $db->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
                $ins->bind_param('ssss', $name, $email, $hash, $role);
                if ($ins->execute()) {
                    $success = 'Account created! You can now log in.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CHRAS — Login</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<canvas id="bgCanvas"></canvas>
<div class="page-wrap">

<div class="login-screen">
  <div class="login-card">

    <div class="login-header">
      <div class="login-logo">CHRAS</div>
      <div class="login-sub">Community Health Reporting &amp; Alert System<br>Kiambu County — <?= date('Y') ?></div>
    </div>

    <!-- Mode Toggle -->
    <div style="display:flex;gap:8px;margin-bottom:24px;">
      <button onclick="setMode('login')"    id="btn_login"    class="btn btn-primary btn-sm" style="flex:1">Login</button>
      <button onclick="setMode('register')" id="btn_register" class="btn btn-ghost btn-sm"   style="flex:1">Register</button>
    </div>

    <?php if ($error):   ?><div class="info-box" style="border-color:var(--red);color:var(--red);margin-bottom:16px;">⚠ <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="info-box" style="margin-bottom:16px;">✅ <?= $success ?></div><?php endif; ?>

    <form method="POST" id="authForm">
      <input type="hidden" name="mode" id="modeInput" value="<?= $mode ?>">

      <!-- Register-only fields -->
      <div id="registerFields" style="display:<?= $mode==='register'?'block':'none' ?>">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name']??'') ?>" placeholder="Jane Wanjiku">
        </div>
        <div class="form-group">
          <label>Role</label>
          <div class="role-select" id="roleSelect">
            <div class="role-option <?= ($_POST['role']??'resident')==='resident'?'active':'' ?>" onclick="pickRole('resident',this)">
              <span>👤</span>Resident
            </div>
            <div class="role-option <?= ($_POST['role']??'')==='officer'?'active':'' ?>" onclick="pickRole('officer',this)">
              <span>🏥</span>Officer
            </div>
            <div class="role-option <?= ($_POST['role']??'')==='admin'?'active':'' ?>" onclick="pickRole('admin',this)">
              <span>⚙️</span>Admin
            </div>
          </div>
          <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role']??'resident') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>" placeholder="you@email.com" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">
        <span id="btnLabel"><?= $mode==='register'?'Create Account':'Enter System →' ?></span>
      </button>
    </form>

    <div class="info-box" style="margin-top:20px;">
      <strong>Demo credentials:</strong><br>
      Admin: darwinanker27@gmail.com / password<br>
      Officer: officer@chras.go.ke / password
    </div>

  </div>
</div>

<div id="toast"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="js/background.js"></script>
<script>
function setMode(m) {
  document.getElementById('modeInput').value = m;
  document.getElementById('registerFields').style.display = m === 'register' ? 'block' : 'none';
  document.getElementById('btnLabel').textContent = m === 'register' ? 'Create Account' : 'Enter System →';
  document.getElementById('btn_login').className    = 'btn btn-sm ' + (m==='login'    ? 'btn-primary' : 'btn-ghost');
  document.getElementById('btn_register').className = 'btn btn-sm ' + (m==='register' ? 'btn-primary' : 'btn-ghost');
}
function pickRole(r, el) {
  document.getElementById('roleInput').value = r;
  document.querySelectorAll('.role-option').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
}
<?php if ($mode): ?>setMode('<?= $mode ?>');<?php endif; ?>
</script>
</div>
</body>
</html>
