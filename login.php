<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn())      redirect(SITE_URL.'/patient/dashboard.php');
if (isAdminLoggedIn()) redirect(SITE_URL.'/admin/dashboard.php');
if (isset($_GET['cancel_otp'])) { unset($_SESSION['otp_pending']); redirect(SITE_URL.'/login.php'); }

$error = '';
$step  = $_SESSION['otp_pending']['step'] ?? null;

// ── STEP 2: Verify OTP ───────────────────────────────────────────
if ($step === 'otp' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step']??'') === 'otp') {
    verifyCsrf();
    $pending = $_SESSION['otp_pending'] ?? [];
    $code    = preg_replace('/\D/', '', trim($_POST['otp_code'] ?? ''));

    if (strlen($code) !== 6) {
        $error = 'Please enter the complete 6-digit code from your Gmail.';
    } else {
        $valid = verifyOtp((int)$pending['id'], $pending['role'], $code);
        if ($valid) {
            if ($pending['role'] === 'admin') {
                $_SESSION['admin_id']    = $pending['id'];
                $_SESSION['admin_name']  = $pending['name'];
                $_SESSION['admin_email'] = $pending['email'];
                $_SESSION['role']        = 'admin';
            } else {
                $_SESSION['user_id']    = $pending['id'];
                $_SESSION['user_name']  = $pending['name'];
                $_SESSION['user_email'] = $pending['email'];
                $_SESSION['role']       = 'patient';
            }
            unset($_SESSION['otp_pending']);
            clearRateLimit($pending['email']);
            auditLog($pending['role'], (int)$pending['id'], 'login_otp_success');
            redirect($pending['role'] === 'admin'
                ? SITE_URL.'/admin/dashboard.php'
                : SITE_URL.'/patient/dashboard.php');
        } else {
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;
            if ($pending['attempts'] >= 5) {
                unset($_SESSION['otp_pending']);
                $error = 'Too many incorrect attempts. Please start over.';
                $step  = null;
            } else {
                $_SESSION['otp_pending'] = $pending;
                $left  = 5 - $pending['attempts'];
                $error = 'Invalid or expired code. Check your Gmail inbox. ' . $left . ' attempt(s) remaining.';
            }
            auditLog($pending['role'] ?? 'patient', (int)($pending['id'] ?? 0), 'login_otp_fail');
        }
    }
}

// ── RESEND ───────────────────────────────────────────────────────
if ($step === 'otp' && isset($_GET['resend'])) {
    $pending = $_SESSION['otp_pending'] ?? [];
    if (!empty($pending['email'])) {
        $newOtp = generateOtp((int)$pending['id'], $pending['role']);
        $sent   = sendOtpEmail($pending['email'], $pending['name'], $newOtp, $pending['role']);
        flashMessage($sent ? 'success' : 'error',
            $sent ? 'New code sent to ' . $pending['email'] . '. Check your Gmail.'
                  : 'Could not send email. Check MAIL_FROM and MAIL_PASS in config.php.');
    }
    redirect(SITE_URL . '/login.php');
}

// ── STEP 1: Email + Password → detect role → send OTP ────────────
if (!$step && $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['step'] ?? '') === '')) {
    verifyCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } elseif (!checkRateLimit($email)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $db   = getDB();
        $user = null;
        $role = null;

        // Check admins table first
        $s = $db->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        $row = $s->fetch();
        if ($row && password_verify($password, $row['password'])) {
            $user = $row;
            $role = 'admin';
        }

        // If not admin, check patients table
        if (!$user) {
            $s = $db->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
            $s->execute([$email]);
            $row = $s->fetch();
            if ($row && password_verify($password, $row['password'])) {
                $user = $row;
                $role = 'patient';
            }
        }

        if ($user && $role) {
            clearRateLimit($email);
            $displayName = ($role === 'admin') ? $user['full_name'] : decryptField($user['full_name_enc']);
            $otp  = generateOtp((int)$user['id'], $role);
            $sent = sendOtpEmail($email, $displayName, $otp, $role);

            if ($sent) {
                $_SESSION['otp_pending'] = [
                    'id'       => $user['id'],
                    'name'     => $displayName,
                    'email'    => $email,
                    'role'     => $role,
                    'step'     => 'otp',
                    'attempts' => 0,
                    'sent_at'  => time(),
                ];
                $step = 'otp';
                auditLog($role, (int)$user['id'], 'login_otp_sent');
            } else {
                $error = 'Login correct but Gmail OTP could not be sent. Check MAIL_FROM and MAIL_PASS in config.php.';
                auditLog($role, (int)$user['id'], 'login_otp_send_failed');
            }
        } else {
            recordFailedLogin($email);
            auditLog('unknown', null, 'login_fail', null, null, $email);
            $error = 'Invalid email or password.';
            usleep(random_int(200000, 500000));
        }
    }
}

$flash   = getFlash();
$pending = $_SESSION['otp_pending'] ?? [];

// Mask email for display e.g. ky***o@gmail.com
$maskedEmail = '';
if (!empty($pending['email'])) {
    [$namePart, $domain] = array_pad(explode('@', $pending['email'], 2), 2, '');
    $masked      = substr($namePart, 0, 2) . str_repeat('*', max(0, strlen($namePart) - 3)) . substr($namePart, -1);
    $maskedEmail = $masked . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $step==='otp' ? 'Verify Code' : 'Sign In' ?> — Clinic Appointment System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#0a1628;--navy2:#1e3a52;--primary:#1a6b8a;--primary-dark:#135470;
  --accent:#0ea5c9;--border:#dde5ed;--muted:#4a6480;--light:#8aa5be;
  --danger:#dc2626;--success:#16a34a;--text:#0f2233;
}
html,body{min-height:100%;font-family:'DM Sans',sans-serif}

/* ── PAGE BG ─────────────────────────────────────────── */
.page{
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:24px;position:relative;overflow:hidden;
  background:
    linear-gradient(160deg,rgba(10,22,40,.96) 0%,rgba(10,22,40,.82) 45%,rgba(14,60,80,.9) 100%),
    url('https://scontent.filo1-1.fna.fbcdn.net/v/t1.15752-9/754849415_2946117705724860_2187227681828014359_n.jpg?stp=dst-jpg_tt6&cstp=mx1086x1448&ctp=s1086x1448&_nc_cat=110&ccb=1-7&_nc_sid=9f807c&_nc_eui2=AeE5hNWlWFyzXZjlwvh-aTo1ouwEAnuCtNOi7AQCe4K00zi4hXgLZibrLWg1YP5f9K8JIGfBEwmkJZShrhcixLX3&_nc_ohc=11sGOmJhzN0Q7kNvwFzKYuc&_nc_oc=AdpPyfGFdYGNGuAdjQvGTJk63KVrX9NSH84PDitZcLzdKN9BWkCguGHPA48vOVCNlfA&_nc_zt=23&_nc_ht=scontent.filo1-1.fna&_nc_ss=7b2a8&oh=03_Q7cD5wH-KCOWe8Tu_GDokm0eQHw9VXm61co3PLMP8rsYRvvUYw&oe=6A8F7472') center/cover no-repeat fixed; 
  
}
/* floating glow blobs */
.page::before{content:'';position:absolute;width:500px;height:500px;background:radial-gradient(circle,rgba(14,165,201,.1) 0%,transparent 70%);top:-100px;right:-100px;border-radius:50%;pointer-events:none}
.page::after{content:'';position:absolute;width:350px;height:350px;background:radial-gradient(circle,rgba(26,107,138,.08) 0%,transparent 70%);bottom:-60px;left:10%;border-radius:50%;pointer-events:none}

/* ── TOP BRAND ───────────────────────────────────────── */
.top-bar{position:fixed;top:0;left:0;right:0;display:flex;align-items:center;justify-content:space-between;padding:0 40px;height:66px;background:rgba(10,22,40,.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,.06);z-index:100}
.brand{display:flex;align-items:center;gap:11px;text-decoration:none}
.brand-logo{width:38px;height:38px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.brand-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#fff}
.brand-sub{font-size:10px;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase}
.back-btn{display:flex;align-items:center;gap:6px;font-size:13px;color:rgba(255,255,255,.5);text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.12);transition:.2s}
.back-btn:hover{color:#fff;border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.06)}

/* ── CARD ────────────────────────────────────────────── */
.card{
  background:#fff;border-radius:24px;width:100%;max-width:430px;
  box-shadow:0 40px 100px rgba(0,0,0,.45);
  overflow:hidden;position:relative;z-index:1;
  animation:card-rise .45s cubic-bezier(.34,1.56,.64,1);
  margin-top:66px;
}
@keyframes card-rise{from{opacity:0;transform:translateY(28px) scale(.96)}to{opacity:1;transform:none}}

/* ── CARD HEADER ─────────────────────────────────────── */
.card-head{
  background:linear-gradient(135deg,var(--navy) 0%,#1a3a55 100%);
  padding:32px 36px 26px;text-align:center;position:relative;
}
.head-icon{width:58px;height:58px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 24px rgba(0,0,0,.25)}
.head-icon-primary{background:linear-gradient(135deg,var(--primary),var(--accent))}
.head-icon-gmail{background:#fff;border-radius:50%}
.head-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;color:#fff;margin-bottom:6px}
.head-sub{font-size:13px;color:rgba(255,255,255,.5);line-height:1.6;max-width:280px;margin:0 auto}

/* ── CARD BODY ───────────────────────────────────────── */
.card-body{padding:30px 36px 36px}

/* ── ALERTS ──────────────────────────────────────────── */
.alert{padding:12px 15px;border-radius:10px;font-size:13px;margin-bottom:18px;display:flex;align-items:flex-start;gap:9px;line-height:1.55;border:1px solid transparent}
.alert-error  {background:#fef2f2;color:#b91c1c;border-color:#fecaca}
.alert-success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.alert-info   {background:#e8f4f8;color:#135470;border-color:#9ecfe0}

/* ── FORM ────────────────────────────────────────────── */
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.07em}
.form-control{
  width:100%;padding:12px 15px;
  border:1.5px solid var(--border);border-radius:10px;
  font-family:'DM Sans',sans-serif;font-size:14px;color:var(--text);
  background:#fafbfc;outline:none;transition:.2s;
}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,138,.1);background:#fff}
.form-control::placeholder{color:var(--light)}
.pw-wrap{position:relative}
.pw-wrap .form-control{padding-right:46px}
.pw-eye{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--light);padding:4px;transition:.15s}
.pw-eye:hover{color:var(--primary)}

/* ── OTP BOX INPUT ───────────────────────────────────── */
.otp-sent-box{
  background:linear-gradient(135deg,#e8f4f8,#f0f9ff);
  border:1.5px solid #9ecfe0;border-radius:14px;
  padding:16px 18px;margin-bottom:22px;text-align:center;
}
.gmail-pill{
  display:inline-flex;align-items:center;gap:8px;
  background:#fff;border:1px solid #e0e0e0;border-radius:100px;
  padding:6px 16px;font-size:13px;font-weight:600;color:#333;
  margin-bottom:10px;box-shadow:0 2px 8px rgba(0,0,0,.07);
}
.otp-sent-box p{font-size:13px;color:#135470;line-height:1.6}
.otp-digits-row{display:grid;grid-template-columns:repeat(6,1fr);gap:9px;margin-bottom:8px}
.otp-dig{
  width:100%;aspect-ratio:1;
  font-size:22px;font-weight:700;text-align:center;
  border:2px solid var(--border);border-radius:11px;
  font-family:monospace;color:var(--text);
  outline:none;transition:.2s;background:#fafbfc;
  caret-color:var(--primary);
}
.otp-dig:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,138,.13);background:#fff}
.otp-dig.has-val{border-color:var(--primary);background:#e8f4f8;color:var(--primary)}
.otp-status{text-align:center;font-size:13px;min-height:18px;margin-top:4px;transition:.2s}

/* ── SUBMIT BTN ──────────────────────────────────────── */
.btn-submit{
  width:100%;padding:14px;border:none;border-radius:11px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;
  box-shadow:0 6px 20px rgba(26,107,138,.38);transition:all .25s;margin-top:6px;
  letter-spacing:.01em;
}
.btn-submit:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 10px 30px rgba(26,107,138,.48)}
.btn-submit:disabled{opacity:.45;cursor:not-allowed;transform:none}
.btn-submit.ready{background:linear-gradient(135deg,var(--success),#15803d);box-shadow:0 6px 20px rgba(22,163,74,.35)}

/* ── GMAIL INFO STRIP ────────────────────────────────── */
.gmail-strip{
  display:flex;align-items:center;gap:9px;
  background:#f8fafb;border:1px solid var(--border);
  border-radius:10px;padding:11px 14px;
  font-size:13px;color:var(--muted);margin-top:16px;
}

/* ── LINKS ───────────────────────────────────────────── */
.link-row{text-align:center;margin-top:18px;font-size:13px;color:var(--muted)}
.link-row a{color:var(--primary);font-weight:600;text-decoration:none}
.link-row a:hover{text-decoration:underline}
.resend-link{display:none;text-align:center;margin-top:14px;font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;padding:7px;border-radius:8px;transition:.15s}
.resend-link:hover{background:#e8f4f8}
.timer-txt{font-size:12px;color:var(--light);text-align:center;margin-top:10px}
.timer-txt .tc{font-weight:700;color:var(--danger)}

/* ── CARD FOOTER BADGES ──────────────────────────────── */
.card-foot{background:#f8fafb;border-top:1px solid #eef2f6;padding:13px 36px;display:flex;justify-content:center;gap:22px;flex-wrap:wrap}
.foot-badge{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--light);font-weight:500}

/* ── DEMO BOX ────────────────────────────────────────── */
.demo-box{background:#f8fafb;border:1px solid var(--border);border-radius:9px;padding:11px 14px;font-size:12px;color:var(--muted);margin-top:14px;line-height:2.1}
</style>
</head>
<body>
<div class="top-bar">
  <a href="<?= SITE_URL ?>/index.php" class="brand">
    <div class="brand-logo" style="background:transparent;padding:0"><img src="<?= SITE_URL ?>/assets/jelan_logo.jpg" alt="Jelan Logo" style="width:38px;height:38px;object-fit:contain;border-radius:8px;display:block"></div>
    <div><div class="brand-name">Jelan Pharmacy &amp; Medical Laboratory</div><div class="brand-sub">"All-in-One Health Services under one roof"</div></div>
  </a>
  <a href="<?= SITE_URL ?>/index.php" class="back-btn">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to home
  </a>
</div>

<div class="page">
  <div class="card">

    <?php if ($step === 'otp'): ?>
    <!-- ════════════ OTP STEP ════════════ -->
    <div class="card-head">
      <div class="head-icon head-icon-gmail">
        <!-- Gmail logo -->
        <svg width="34" height="26" viewBox="52 42 88 66" xmlns="http://www.w3.org/2000/svg">
          <path fill="#4285F4" d="M58 108h14V74L52 59v43c0 3.32 2.69 6 6 6z"/>
          <path fill="#34A853" d="M120 108h14c3.32 0 6-2.69 6-6V59l-20 15z"/>
          <path fill="#FBBC04" d="M120 48v26l20-15v-8c0-7.42-8.47-11.65-14.4-7.2z"/>
          <path fill="#EA4335" d="M72 74V48l24 18 24-18v26L96 92z"/>
          <path fill="#C5221F" d="M52 51v8l20 15V48l-5.6-4.2C60.47 39.35 52 43.58 52 51z"/>
        </svg>
      </div>
      <div class="head-title">Check your Gmail</div>
      <div class="head-sub">We sent a 6-digit verification code to your inbox. Enter it below to sign in.</div>
    </div>

    <div class="card-body">
      <?php if ($error): ?>
      <div class="alert alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= sanitize($error) ?>
      </div>
      <?php endif; ?>
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="otp-sent-box">
        <div class="gmail-pill">
          <svg width="18" height="14" viewBox="52 42 88 66" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M58 108h14V74L52 59v43c0 3.32 2.69 6 6 6z"/><path fill="#34A853" d="M120 108h14c3.32 0 6-2.69 6-6V59l-20 15z"/><path fill="#FBBC04" d="M120 48v26l20-15v-8c0-7.42-8.47-11.65-14.4-7.2z"/><path fill="#EA4335" d="M72 74V48l24 18 24-18v26L96 92z"/><path fill="#C5221F" d="M52 51v8l20 15V48l-5.6-4.2C60.47 39.35 52 43.58 52 51z"/></svg>
          Code sent to <strong style="margin-left:3px"><?= sanitize($maskedEmail) ?></strong>
        </div>
        <p>Open Gmail &rarr; look for <strong>Clinic</strong> email<br>
        Expires in <strong style="color:var(--danger)"><?= OTP_EXPIRY ?> minutes</strong></p>
      </div>

      <form method="POST" id="otpForm" onsubmit="return beforeSubmit()">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="otp">
        <input type="hidden" name="otp_code" id="otpHidden">

        <div class="form-group">
          <label style="text-align:center;display:block;margin-bottom:10px">Enter 6-digit code</label>
          <div class="otp-digits-row" id="digitRow">
            <?php for($i=0;$i<6;$i++): ?>
            <input type="text" class="otp-dig" id="d<?=$i?>"
              maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="<?=$i===0?'one-time-code':'off'?>"
              tabindex="<?=$i+1?>">
            <?php endfor; ?>
          </div>
          <div class="otp-status" id="otpStatus" style="color:var(--light)">Type or paste your code above</div>
        </div>

        <button type="submit" class="btn-submit" id="verifyBtn" disabled>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Verify &amp; Sign In
        </button>
      </form>

      <div class="timer-txt" id="timerTxt">Code expires in <span class="tc" id="timerVal"><?= OTP_EXPIRY*60 ?></span>s</div>
      <a href="<?= SITE_URL ?>/login.php?resend=1" class="resend-link" id="resendLink">&#128257; Resend code to Gmail</a>

      <div class="link-row">
        <a href="<?= SITE_URL ?>/login.php?cancel_otp=1">&larr; Use a different account</a>
      </div>
    </div>

    <?php else: ?>
    <!-- ════════════ LOGIN STEP ════════════ -->
    <div class="card-head">
      <div class="head-icon head-icon-primary" style="background:transparent;box-shadow:none;padding:0"><img src="<?= SITE_URL ?>/assets/jelan_logo.jpg" alt="Jelan Logo" style="width:58px;height:58px;object-fit:contain;border-radius:12px;display:block"></div>
      <div class="head-title">Welcome</div>
      <div class="head-sub">Sign in to Clinic</div>
    </div>

    <div class="card-body">
      <?php if ($error): ?>
      <div class="alert alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= sanitize($error) ?>
      </div>
      <?php endif; ?>
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" class="form-control"
            placeholder="you@gmail.com"
            value="<?= sanitize($_POST['email'] ?? '') ?>"
            required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pwField" class="form-control"
              placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
            <button type="button" class="pw-eye" onclick="togglePw()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <svg width="18" height="14" viewBox="52 42 88 66" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M58 108h14V74L52 59v43c0 3.32 2.69 6 6 6z"/><path fill="#34A853" d="M120 108h14c3.32 0 6-2.69 6-6V59l-20 15z"/><path fill="#FBBC04" d="M120 48v26l20-15v-8c0-7.42-8.47-11.65-14.4-7.2z"/><path fill="#EA4335" d="M72 74V48l24 18 24-18v26L96 92z"/><path fill="#C5221F" d="M52 51v8l20 15V48l-5.6-4.2C60.47 39.35 52 43.58 52 51z"/></svg>
          Sign In &amp; Send Gmail OTP
        </button>
      </form>

      <div class="gmail-strip">
        After signing in, a 6-digit OTP will be sent to your Gmail inbox
      </div>

      <div class="link-row">
        Don't have an account? <a href="<?= SITE_URL ?>/register.php">Create one free</a>
      </div>
    </div>
    <?php endif; ?>


  </div><!-- /card -->
</div><!-- /page -->

<script>
// ── Password toggle ──────────────────────────────────────────
function togglePw(){var f=document.getElementById('pwField');f.type=f.type==='password'?'text':'password';}

// ── OTP digit boxes ──────────────────────────────────────────
var digs     = document.querySelectorAll('.otp-dig');
var hidden   = document.getElementById('otpHidden');
var btn      = document.getElementById('verifyBtn');
var statusEl = document.getElementById('otpStatus');

function getCode(){
  var c=''; digs.forEach(function(d){c+=d.value;}); return c;
}

function syncHidden(){
  var code = getCode();
  if(hidden) hidden.value = code;
  return code;
}

function updateUI(){
  var code = syncHidden();
  var full = code.length===6 && /^\d{6}$/.test(code);
  if(btn){
    btn.disabled = !full;
    btn.classList.toggle('ready', full);
    if(full){
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Verify &amp; Sign In';
    } else {
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Verify &amp; Sign In';
    }
  }
  digs.forEach(function(d){d.classList.toggle('has-val', d.value!=='');});
  if(statusEl){
    if(full){statusEl.textContent='✓ Code ready — click Verify to sign in';statusEl.style.color='#16a34a';statusEl.style.fontWeight='600';}
    else if(code.length>0){statusEl.textContent=code.length+' of 6 digits entered';statusEl.style.color='#1a6b8a';statusEl.style.fontWeight='400';}
    else{statusEl.textContent='Type or paste your code above';statusEl.style.color='#8aa5be';statusEl.style.fontWeight='400';}
  }
}

function beforeSubmit(){
  var code = syncHidden();
  if(code.length!==6||!/^\d{6}$/.test(code)){
    if(statusEl){statusEl.textContent='Please enter all 6 digits.';statusEl.style.color='#dc2626';}
    return false;
  }
  return true;
}

if(digs.length){
  digs.forEach(function(d,i){
    d.addEventListener('input',function(){
      this.value=this.value.replace(/\D/g,'').slice(-1);
      if(this.value&&i<5) digs[i+1].focus();
      updateUI();
    });
    d.addEventListener('keydown',function(e){
      if(e.key==='Backspace'){if(!this.value&&i>0){digs[i-1].focus();digs[i-1].value='';} updateUI();}
      if(e.key==='ArrowLeft'&&i>0) digs[i-1].focus();
      if(e.key==='ArrowRight'&&i<5) digs[i+1].focus();
    });
    d.addEventListener('paste',function(e){
      e.preventDefault();
      var t=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
      for(var j=0;j<t.length;j++){if(digs[j])digs[j].value=t[j];}
      digs[Math.min(t.length,5)].focus();
      updateUI();
    });
    d.addEventListener('focus',function(){this.select();});
  });
  digs[0].focus();
}

// ── Countdown timer ──────────────────────────────────────────
var timerEl  = document.getElementById('timerVal');
var timerTxt = document.getElementById('timerTxt');
var resendLk = document.getElementById('resendLink');
if(timerEl){
  var secs=parseInt(timerEl.textContent,10);
  var iv=setInterval(function(){
    secs--;
    if(secs<=0){
      clearInterval(iv);
      if(timerTxt) timerTxt.innerHTML='<span style="color:#dc2626;font-weight:600">&#9888; Code expired — please resend.</span>';
      if(resendLk){resendLk.style.display='block';}
      if(btn) btn.disabled=true;
    } else {
      var m=Math.floor(secs/60),s=secs%60;
      timerEl.textContent=(m>0?m+'m ':'')+s+'s';
      if(secs<=60&&resendLk) resendLk.style.display='block';
    }
  },1000);
}
</script>
</body>
</html>
