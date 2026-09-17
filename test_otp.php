<?php
/**
 * CyberClinic — OTP Debug & Test Tool
 * Visit: http://localhost/cyberclinic/test_otp.php
 * DELETE this file after fixing!
 */
require_once __DIR__ . '/includes/config.php';
$result = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_test') {
        // Generate OTP for admin user (id=1)
        $userId = 1; $role = 'admin';
        $otp = generateOtp($userId, $role);
        // Show what was stored
        $db  = getDB();
        $row = $db->prepare('SELECT * FROM otp_codes WHERE user_id=? AND user_type=? ORDER BY created_at DESC LIMIT 1');
        $row->execute([$userId, $role]);
        $stored = $row->fetch();
        $result = "Generated OTP: $otp\n";
        $result .= "Stored hash: " . ($stored['otp_hash'] ?? 'NOT FOUND') . "\n";
        $result .= "Expires at: " . ($stored['expires_at'] ?? 'N/A') . "\n";
        $result .= "Current PHP time: " . date('Y-m-d H:i:s') . "\n";
        $result .= "Used: " . ($stored['used'] ?? 'N/A') . "\n";
        $result .= "\nNow verify it below with code: $otp";
    }

    if ($action === 'verify_test') {
        $userId = 1; $role = 'admin';
        $code   = preg_replace('/\D/', '', trim($_POST['test_code'] ?? ''));
        $result = "Verifying code: '$code' for user $userId ($role)\n";
        $result .= "PHP time: " . date('Y-m-d H:i:s') . "\n\n";

        // Show stored OTP
        $db  = getDB();
        $row = $db->prepare('SELECT * FROM otp_codes WHERE user_id=? AND user_type=? AND used=0 ORDER BY created_at DESC LIMIT 1');
        $row->execute([$userId, $role]);
        $stored = $row->fetch();
        if ($stored) {
            $result .= "Found OTP record:\n";
            $result .= "  - expires_at: {$stored['expires_at']}\n";
            $result .= "  - expires in " . (strtotime($stored['expires_at']) - time()) . " seconds\n";
            $result .= "  - used: {$stored['used']}\n";
            $result .= "  - hash type: " . (strpos($stored['otp_hash'],':') !== false ? 'SHA256 (new)' : 'bcrypt (old)') . "\n\n";

            // Test hash manually
            $storedHash = $stored['otp_hash'];
            if (strpos($storedHash, ':') !== false) {
                [$salt, $expected] = explode(':', $storedHash, 2);
                $computed = hash('sha256', $salt . $code);
                $result .= "SHA256 verify:\n";
                $result .= "  - expected: $expected\n";
                $result .= "  - computed: $computed\n";
                $result .= "  - match: " . ($expected === $computed ? 'YES ✓' : 'NO ✗') . "\n\n";
            } else {
                $result .= "bcrypt verify: " . (password_verify($code, $storedHash) ? 'YES ✓' : 'NO ✗') . "\n\n";
            }
        } else {
            $result .= "NO OTP record found! Generate one first.\n";
        }

        $valid = verifyOtp($userId, $role, $code);
        $result .= "verifyOtp() result: " . ($valid ? '✓ VALID — Login would succeed!' : '✗ INVALID') . "\n";
    }

    if ($action === 'check_timezone') {
        $db  = getDB();
        $tzRow = $db->query("SELECT NOW() AS mysql_now, @@global.time_zone AS tz, @@session.time_zone AS sess_tz")->fetch();
        $result  = "PHP time:       " . date('Y-m-d H:i:s') . "\n";
        $result .= "MySQL NOW():    " . $tzRow['mysql_now'] . "\n";
        $result .= "MySQL timezone: " . $tzRow['tz'] . "\n";
        $result .= "Session tz:     " . $tzRow['sess_tz'] . "\n\n";
        $phpTs  = time();
        $mysqlTs = strtotime($tzRow['mysql_now']);
        $diff   = abs($phpTs - $mysqlTs);
        $result .= "Time difference: {$diff} seconds\n";
        $result .= $diff > 60 ? "⚠ TIMEZONE MISMATCH! This causes OTP expiry failures.\n" : "✓ Timezones are in sync.\n";
    }

    if ($action === 'clear_otps') {
        getDB()->exec('DELETE FROM otp_codes');
        $result = "✓ All OTP records cleared from database.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CyberClinic OTP Debug Tool</title>
<style>
body{font-family:sans-serif;background:#f0f4f8;padding:24px}
.card{background:#fff;border-radius:12px;padding:24px;max-width:700px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.08)}
h2{color:#0f2233;margin-bottom:6px}
.sub{color:#4a6480;font-size:13px;margin-bottom:20px}
.section{background:#f8fafb;border:1px solid #dde5ed;border-radius:8px;padding:16px;margin-bottom:14px}
.section h3{font-size:14px;font-weight:700;color:#0f2233;margin-bottom:10px}
pre{background:#0f2233;color:#7dd3fc;padding:14px;border-radius:8px;font-size:12px;line-height:1.8;white-space:pre-wrap;margin-top:10px;overflow-x:auto}
.btn{padding:9px 20px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;margin-right:6px}
.btn-blue{background:#1a6b8a;color:#fff}
.btn-green{background:#16a34a;color:#fff}
.btn-red{background:#dc2626;color:#fff}
.btn-gray{background:#6b7280;color:#fff}
input[type=text]{padding:9px 12px;border:1.5px solid #dde5ed;border-radius:7px;font-size:14px;width:180px;font-family:monospace;letter-spacing:3px;text-align:center;outline:none}
input[type=text]:focus{border-color:#1a6b8a}
.warn{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:12px;color:#92400e;margin-top:16px}
</style>
</head>
<body>
<div class="card">
  <h2>🔧 CyberClinic OTP Debug Tool</h2>
  <div class="sub">Use this to diagnose why OTP verification is failing. Delete this file after fixing!</div>

  <?php if ($result): ?><pre><?= htmlspecialchars($result) ?></pre><?php endif; ?>
  <?php if ($error):  ?><pre style="background:#dc2626"><?= htmlspecialchars($error) ?></pre><?php endif; ?>

  <div class="section">
    <h3>Step 1 — Check timezone sync</h3>
    <p style="font-size:13px;color:#4a6480;margin-bottom:10px">Timezone mismatch causes OTP to appear expired even when it's not.</p>
    <form method="POST"><input type="hidden" name="action" value="check_timezone"><button type="submit" class="btn btn-gray">Check Timezone</button></form>
  </div>

  <div class="section">
    <h3>Step 2 — Generate test OTP (for admin user #1)</h3>
    <form method="POST"><input type="hidden" name="action" value="generate_test"><button type="submit" class="btn btn-blue">Generate Test OTP</button></form>
  </div>

  <div class="section">
    <h3>Step 3 — Verify the code</h3>
    <p style="font-size:13px;color:#4a6480;margin-bottom:10px">Enter the code shown above (or from your Gmail) and click Verify:</p>
    <form method="POST" style="display:flex;gap:10px;align-items:center">
      <input type="hidden" name="action" value="verify_test">
      <input type="text" name="test_code" placeholder="123456" maxlength="6" inputmode="numeric" required autofocus>
      <button type="submit" class="btn btn-green">Verify Code</button>
    </form>
  </div>

  <div class="section">
    <h3>Step 4 — Clear all OTP records (start fresh)</h3>
    <form method="POST"><input type="hidden" name="action" value="clear_otps"><button type="submit" class="btn btn-red" onclick="return confirm('Clear all OTPs?')">Clear All OTPs</button></form>
  </div>

  <div class="warn">⚠️ <strong>Delete this file after fixing!</strong> C:\xampp\htdocs\cyberclinic\test_otp.php</div>
</div>
</body>
</html>
