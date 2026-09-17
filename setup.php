<?php
/**
 * CyberClinic — Setup & Repair Tool
 * Visit: http://localhost/cyberclinic/setup.php
 * DELETE THIS FILE immediately after use!
 */
require_once __DIR__ . '/includes/config.php';
$results = [];
$error   = '';
$SETUP_SECRET = 'CyberClinic2024Setup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_password') {
        $secret = $_POST['setup_secret'] ?? '';
        $newPw  = $_POST['new_password'] ?? '';
        $conf   = $_POST['confirm_password'] ?? '';
        if ($secret !== $SETUP_SECRET)     { $error = 'Wrong setup secret.'; }
        elseif ($newPw !== $conf)          { $error = 'Passwords do not match.'; }
        elseif (!isStrongPassword($newPw)) { $error = 'Password too weak. Need uppercase, lowercase, number and symbol.'; }
        else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 10]);
            getDB()->prepare("UPDATE admins SET password=? WHERE email='admin@cyberclinic.com'")->execute([$hash]);
            $results[] = '✓ Admin password updated for admin@cyberclinic.com';
            $results[] = '';
            $results[] = '✅ SUCCESS — delete this file now!';
        }
    }

    if ($action === 'fix_mfa') {
        try {
            $db = getDB();
            $db->exec('ALTER TABLE users  MODIFY COLUMN mfa_secret VARCHAR(255)');
            $results[] = '✓ users.mfa_secret expanded to VARCHAR(255)';
            $db->exec('ALTER TABLE admins MODIFY COLUMN mfa_secret VARCHAR(255)');
            $results[] = '✓ admins.mfa_secret expanded to VARCHAR(255)';
            $broken = $db->query("SELECT COUNT(*) FROM users WHERE mfa_enabled=1 AND (mfa_secret IS NULL OR LENGTH(mfa_secret)<80)")->fetchColumn();
            if ($broken > 0) {
                $db->exec("UPDATE users SET mfa_enabled=0,mfa_secret=NULL,mfa_backup_codes=NULL WHERE mfa_enabled=1 AND (mfa_secret IS NULL OR LENGTH(mfa_secret)<80)");
                $results[] = "✓ Reset $broken broken MFA account(s)";
            } else {
                $results[] = '✓ No broken MFA accounts found';
            }
            $results[] = '';
            $results[] = '✅ MFA fix complete! Re-enable 2FA from Profile → Security.';
            $results[] = '⚠️  DELETE this file after use!';
        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    }

    if ($action === 'check_python') {
        $py = checkPythonAvailable();
        if ($py['available']) {
            $results[] = '✓ Python found: ' . $py['version'];
            $results[] = '✓ Command: ' . $py['command'];
            $cr = shell_exec(escapeshellcmd($py['command']).' -c "import cryptography; print(\'cryptography \'.concat(cryptography.__version__))" 2>&1');
            $results[] = ($cr && !str_contains($cr,'Error')) ? '✓ '.$cr : '✗ cryptography not installed — run: pip install cryptography';
            $mr = shell_exec(escapeshellcmd($py['command']).' -c "import mysql.connector; print(\'mysql-connector-python \'.concat(mysql.connector.__version__))" 2>&1');
            $results[] = ($mr && !str_contains($mr,'Error')) ? '✓ '.$mr : '✗ mysql-connector-python not installed — run: pip install mysql-connector-python';
            $results[] = '';
            // Test backup script exists
            $scriptPath = __DIR__.'/scripts/backup.py';
            $results[] = file_exists($scriptPath) ? '✓ backup.py found at '.$scriptPath : '✗ backup.py NOT found';
            $scanPath = __DIR__.'/scripts/security_scan.py';
            $results[] = file_exists($scanPath) ? '✓ security_scan.py found at '.$scanPath : '✗ security_scan.py NOT found';
        } else {
            $results[] = '✗ Python not found on this server';
            $results[] = 'Linux: sudo apt install python3';
            $results[] = 'Windows: download from python.org';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Setup — CyberClinic</title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f0f4f8;padding:20px}</style></head>
<body>
<div style="background:#fff;border:1px solid #dde5ed;border-radius:14px;padding:36px;max-width:680px;width:100%;box-shadow:0 4px 20px rgba(15,34,51,.08)">
  <div style="text-align:center;margin-bottom:28px">
    <div style="width:52px;height:52px;background:linear-gradient(135deg,#1a6b8a,#135470);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
    <h2 style="font-family:serif;font-size:22px;color:#0f2233;margin-bottom:5px">CyberClinic Setup &amp; Repair</h2>
    <p style="font-size:13px;color:#4a6480">Admin password reset &bull; MFA fix &bull; Python check</p>
  </div>
  <?php if($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
  <?php if(!empty($results)): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin-bottom:22px;font-family:monospace;font-size:13px;line-height:1.9">
    <?php foreach($results as $r): ?><div><?= htmlspecialchars($r) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div style="border:1px solid #dde5ed;border-radius:10px;padding:18px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;color:#0f2233">&#128272; Set Admin Password</h3>
      <p style="font-size:12px;color:#4a6480;margin-bottom:12px">Setup secret: <code>CyberClinic2024Setup</code></p>
      <form method="POST">
        <input type="hidden" name="action" value="set_password">
        <div class="form-group"><label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#4a6480;display:block;margin-bottom:5px">Setup Secret</label><input type="password" name="setup_secret" class="form-control" required></div>
        <div class="form-group"><label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#4a6480;display:block;margin-bottom:5px">New Password</label><input type="password" name="new_password" class="form-control" required></div>
        <div class="form-group"><label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#4a6480;display:block;margin-bottom:5px">Confirm</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Set Password</button>
      </form>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px">
      <div style="border:1px solid #dde5ed;border-radius:10px;padding:18px;flex:1">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:10px;color:#0f2233">&#128241; Fix MFA (Invalid OTP)</h3>
        <p style="font-size:12px;color:#4a6480;margin-bottom:12px;line-height:1.6">Expands mfa_secret column and resets broken MFA accounts.</p>
        <form method="POST"><input type="hidden" name="action" value="fix_mfa"><button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" onclick="return confirm('Reset broken MFA accounts?')">Fix MFA Now</button></form>
      </div>
      <div style="border:1px solid #dde5ed;border-radius:10px;padding:18px;flex:1">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:10px;color:#0f2233">&#128190; Check Python</h3>
        <p style="font-size:12px;color:#4a6480;margin-bottom:12px;line-height:1.6">Verify Python 3 and backup script dependencies.</p>
        <form method="POST"><input type="hidden" name="action" value="check_python"><button type="submit" class="btn btn-outline" style="width:100%;justify-content:center">Check Python</button></form>
      </div>
    </div>
  </div>
  <div style="margin-top:20px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:12px;color:#92400e">
    <strong>&#9888; Security Warning:</strong> Delete this file immediately after use. It bypasses normal authentication.
  </div>
</div>
</body></html>
