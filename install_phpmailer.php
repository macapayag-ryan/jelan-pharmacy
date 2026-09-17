<?php
/**
 * CyberClinic — PHPMailer Installer
 * ===================================
 * This installs PHPMailer so CyberClinic can send Gmail OTP emails.
 * Visit: http://localhost/cyberclinic/install_phpmailer.php
 * DELETE this file after use!
 */
$root    = __DIR__;
$vendor  = $root . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
$result  = '';
$error   = '';
$step    = $_GET['step'] ?? '';

if ($step === 'install') {
    // Method 1: Composer
    $composerJson = $root . '/composer.json';
    if (!file_exists($composerJson)) {
        file_put_contents($composerJson, json_encode([
            'require' => ['phpmailer/phpmailer' => '^6.9']
        ], JSON_PRETTY_PRINT));
    }
    $output = [];
    exec('composer require phpmailer/phpmailer 2>&1', $output, $code);
    if ($code === 0 && file_exists($vendor)) {
        $result = '✓ PHPMailer installed via Composer!';
    } else {
        // Method 2: Download directly
        $result = implode("\n", $output) . "\n\nTrying direct download...";
        $urls = [
            'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
            'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
            'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php',
        ];
        $dir = $root . '/vendor/phpmailer/phpmailer/src/';
        @mkdir($dir, 0755, true);
        $ok = true;
        foreach ($urls as $url) {
            $fname = basename($url);
            $content = @file_get_contents($url);
            if ($content && file_put_contents($dir . $fname, $content)) {
                $result .= "\n✓ Downloaded: $fname";
            } else {
                $result .= "\n✗ Failed: $fname";
                $ok = false;
            }
        }
        if ($ok) $result .= "\n\n✅ PHPMailer installed via direct download!";
    }
}

if ($step === 'test') {
    $email = trim($_GET['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require_once $root . '/includes/config.php';
        $otp  = '123456'; // test code
        $sent = sendOtpEmail($email, 'Test User', $otp, 'patient');
        if ($sent) {
            $result = "✓ Test email sent to $email!\nCheck your Gmail inbox.\nTest OTP code: 123456";
        } else {
            $error = "Email send failed. Check MAIL_FROM and MAIL_PASS in includes/config.php\n\nMake sure you are using a Gmail App Password (not your Gmail login password).";
        }
    }
}

$phpmailerInstalled = file_exists($vendor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberClinic — PHPMailer Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#fff;border-radius:14px;padding:32px;max-width:680px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.1)}
h2{font-size:20px;color:#0f2233;margin-bottom:6px}
.sub{color:#4a6480;font-size:13px;margin-bottom:24px}
.step{background:#f8fafb;border:1px solid #dde5ed;border-radius:10px;padding:18px;margin-bottom:16px}
.step h3{font-size:15px;font-weight:700;color:#0f2233;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.num{width:24px;height:24px;background:#1a6b8a;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
pre{background:#0f2233;color:#7dd3fc;padding:14px;border-radius:8px;font-size:12px;overflow-x:auto;line-height:1.8;margin:10px 0;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:.2s}
.btn-blue{background:#1a6b8a;color:#fff}.btn-blue:hover{background:#135470}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
input[type=email]{width:100%;padding:10px 14px;border:1.5px solid #dde5ed;border-radius:8px;font-size:14px;margin:8px 0 12px;outline:none}
input[type=email]:focus{border-color:#1a6b8a}
.ok{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;font-size:13px;color:#15803d;white-space:pre-line;margin-bottom:16px}
.err{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px;font-size:13px;color:#b91c1c;white-space:pre-line;margin-bottom:16px}
.badge{display:inline-block;padding:3px 10px;border-radius:100px;font-size:12px;font-weight:700}
.badge-ok{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-no{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.warn{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:12px;color:#92400e;margin-top:16px}
</style>
</head>
<body>
<div class="card">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <div style="width:44px;height:44px;background:linear-gradient(135deg,#1a6b8a,#0ea5c9);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px">📧</div>
    <div><h2>CyberClinic — Gmail OTP Setup</h2><div class="sub">Install PHPMailer and configure Gmail SMTP for OTP login</div></div>
  </div>

  <?php if ($result): ?><div class="ok"><?= htmlspecialchars($result) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="step">
    <h3><span class="num">1</span> PHPMailer Status
      <span class="badge <?= $phpmailerInstalled?'badge-ok':'badge-no' ?>">
        <?= $phpmailerInstalled ? '✓ Installed' : '✗ Not installed' ?>
      </span>
    </h3>
    <?php if (!$phpmailerInstalled): ?>
    <p style="font-size:13px;color:#4a6480;margin-bottom:10px">Click to install PHPMailer (required to send Gmail OTP):</p>
    <a href="?step=install" class="btn btn-blue">Install PHPMailer Now</a>
    <p style="font-size:12px;color:#8aa5be;margin-top:10px">Or manually: open Command Prompt in your cyberclinic folder and run:</p>
    <pre>composer require phpmailer/phpmailer</pre>
    <?php else: ?>
    <p style="font-size:13px;color:#15803d">PHPMailer is installed and ready to use.</p>
    <?php endif; ?>
  </div>

  <div class="step">
    <h3><span class="num">2</span> Gmail App Password Setup</h3>
    <p style="font-size:13px;color:#4a6480;line-height:1.7;margin-bottom:10px">You need a <strong>Gmail App Password</strong> — NOT your regular Gmail password. Follow these steps:</p>
    <pre>1. Go to: myaccount.google.com/security
2. Click "2-Step Verification" → enable it if not yet enabled
3. Click "App passwords" (at the bottom of that page)
4. Select App: "Mail" → Select Device: "Windows Computer"
5. Click Generate → Copy the 16-character password shown
   Example: xxxx xxxx xxxx xxxx</pre>
    <p style="font-size:13px;color:#4a6480;margin-top:10px">Then open <code>cyberclinic/includes/config.php</code> and set:</p>
    <pre>define('MAIL_FROM', 'your-gmail@gmail.com');
define('MAIL_PASS', 'xxxx xxxx xxxx xxxx'); // paste your App Password here</pre>
  </div>

  <div class="step">
    <h3><span class="num">3</span> Test Gmail OTP</h3>
    <p style="font-size:13px;color:#4a6480;margin-bottom:4px">Enter your email to send a test OTP:</p>
    <form action="?step=test" method="GET">
      <input type="hidden" name="step" value="test">
      <input type="email" name="email" placeholder="your-email@gmail.com" required>
      <a onclick="this.closest('form').submit()" href="#" class="btn btn-green">Send Test OTP</a>
    </form>
  </div>

  <div class="step">
    <h3><span class="num">4</span> How Login Works Now</h3>
    <pre style="color:#86efac">
Patient/Admin visits login page
       ↓
Enters email + password
       ↓
If correct → CyberClinic sends 6-digit OTP to their Gmail
       ↓
User opens Gmail → sees OTP email from CyberClinic
       ↓
User enters 6-digit code on the OTP page
       ↓
Code verified → Login successful ✓
(Code expires in <?= OTP_EXPIRY ?> minutes)</pre>
  </div>

  <div class="warn">
    ⚠️ <strong>Delete this file after setup is complete!</strong>
    <code>C:\xampp\htdocs\cyberclinic\install_phpmailer.php</code>
  </div>
</div>
</body>
</html>
