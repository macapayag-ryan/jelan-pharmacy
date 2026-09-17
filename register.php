<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) redirect(SITE_URL.'/patient/dashboard.php');
$error = ''; $fields = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name=$fields['full_name']??''; $email=$fields['email']??''; $password=$fields['password']??''; $confirm=$fields['confirm_pw']??'';
    $birthdate=$fields['birthdate']??''; $sex=$fields['sex']??''; $phone=$fields['phone']??''; $address=$fields['address']??'';
    $blood=$fields['blood_type']??''; $allergies=$fields['allergies']??''; $emergency=$fields['emergency_contact']??'';
    if (empty($name)||empty($email)||empty($password)||empty($birthdate)||empty($sex)) { $error='Name, email, password, date of birth and sex are required.'; }
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $error='Please enter a valid email address.'; }
    elseif ($password!==$confirm) { $error='Passwords do not match.'; }
    elseif (!isStrongPassword($password)) { $error='Password must be at least 8 characters with uppercase, lowercase, number and special character.'; }
    else {
        $db=getDB(); $chk=$db->prepare('SELECT id FROM users WHERE email=?'); $chk->execute([$email]);
        if ($chk->fetch()) { $error='An account with this email already exists.'; }
        else {
            $age=computeAge($birthdate); $hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>10]);
            $db->prepare('INSERT INTO users (email,password,full_name_enc,phone_enc,birthdate_enc,sex_enc,address_enc,emergency_contact_enc,blood_type_enc,allergies_enc,age) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$email,$hash,encryptField($name),encryptField($phone),encryptField($birthdate),encryptField($sex),encryptField($address),encryptField($emergency),encryptField($blood),encryptField($allergies),$age]);
            $newId=(int)$db->lastInsertId();
            auditLog('patient',$newId,'register');
            createNotification($newId,'welcome','Welcome to CyberClinic!','Your account has been created. You can now book appointments with our specialists.');
            flashMessage('success','Account created successfully! Please sign in.');
            redirect(SITE_URL.'/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — CyberClinic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#0a1628;--primary:#1a6b8a;--primary-dark:#135470;--accent:#0ea5c9;--danger:#dc2626;--success:#16a34a;--border:#dde5ed;--text-muted:#4a6480}
html,body{min-height:100%;font-family:'DM Sans',sans-serif}

.page{
  min-height:100vh;
  background:
    linear-gradient(135deg,rgba(10,22,40,.88) 0%,rgba(10,22,40,.72) 45%,rgba(14,60,80,.80) 100%),
    url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1600&q=80&auto=format&fit=crop') center/cover no-repeat fixed;
  display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
  padding:28px 20px 48px;
}

.page-brand{
  align-self:flex-start;display:flex;align-items:center;gap:12px;text-decoration:none;
  margin-bottom:28px;width:100%;max-width:820px;
}
.brand-logo{width:40px;height:40px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.brand-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#fff}
.brand-sub{font-size:10px;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase}
.back-link{margin-left:auto;font-size:13px;color:rgba(255,255,255,.6);text-decoration:none;display:flex;align-items:center;gap:5px;transition:.15s}
.back-link:hover{color:#fff}

.register-card{background:#fff;border-radius:22px;width:100%;max-width:820px;box-shadow:0 32px 80px rgba(0,0,0,.35);overflow:hidden;animation:rise .4s cubic-bezier(.34,1.56,.64,1)}
@keyframes rise{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}

.card-header{background:linear-gradient(135deg,var(--navy) 0%,#1e3a52 100%);padding:28px 36px 24px;display:flex;align-items:center;gap:18px}
.header-logo{width:50px;height:50px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.header-text{}
.card-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:3px}
.card-sub{font-size:13px;color:rgba(255,255,255,.5)}

.card-body{padding:32px 36px 36px}

.section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--primary);margin:0 0 14px;padding-bottom:8px;border-bottom:1.5px solid #e8f4f8;display:flex;align-items:center;gap:7px}
.section-label svg{flex-shrink:0}

.form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:0}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.form-control{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--navy);background:#fff;transition:.2s;outline:none}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,138,.12)}
.form-control::placeholder{color:#8aa5be}
select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%234a6480' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;padding-right:36px;cursor:pointer}
.req{color:var(--danger)}
.pw-wrap{position:relative}
.pw-wrap .form-control{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;transition:.15s}
.pw-toggle:hover{color:var(--primary)}
.pw-strength{font-size:12px;font-weight:600;margin-top:4px;min-height:16px}
.pw-rules{font-size:11px;color:#8aa5be;margin-top:3px}

.enc-notice{display:flex;align-items:center;gap:8px;background:#e8f4f8;border:1px solid #9ecfe0;border-radius:8px;padding:11px 14px;font-size:12px;color:#135470;margin:8px 0 20px}

.btn-submit{
  width:100%;padding:14px 22px;border:none;border-radius:10px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;
  box-shadow:0 4px 16px rgba(26,107,138,.35);transition:all .2s;
}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 22px rgba(26,107,138,.45)}

.alert{padding:12px 15px;border-radius:8px;font-size:13px;margin-bottom:18px;border:1px solid transparent;display:flex;align-items:flex-start;gap:8px}
.alert-error{background:#fef2f2;color:#b91c1c;border-color:#fecaca}

.card-footer{background:#f8fafb;border-top:1px solid #eef2f6;padding:16px 36px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-signin{font-size:13px;color:var(--text-muted)}
.footer-signin a{color:var(--primary);font-weight:600;text-decoration:none}
.footer-signin a:hover{text-decoration:underline}
.footer-badges{display:flex;gap:16px;flex-wrap:wrap}
.sec-badge{display:flex;align-items:center;gap:5px;font-size:11px;color:#8aa5be;font-weight:500}

.sec-col{margin-bottom:24px}

@media(max-width:680px){
  .card-body{padding:24px 20px 28px}
  .card-header{padding:22px 20px}
  .card-footer{padding:14px 20px}
  .form-grid,.form-grid-2{grid-template-columns:1fr}
  .page{padding:20px 12px 36px}
}
</style>
</head>
<body>
<div class="page">

  <!-- Brand -->
  <div class="page-brand">
    <a href="<?= SITE_URL ?>/index.php" style="display:flex;align-items:center;gap:12px;text-decoration:none">
      <div class="brand-logo"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div><div class="brand-name">CyberClinic</div><div class="brand-sub">Secure Medical</div></div>
    </a>
    <a href="<?= SITE_URL ?>/index.php" class="back-link">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Back to home
    </a>
  </div>

  <div class="register-card">
    <!-- HEADER -->
    <div class="card-header">
      <div class="header-logo">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      </div>
      <div class="header-text">
        <div class="card-title">Create your account</div>
        <div class="card-sub">Join CyberClinic — All information is AES-256 encrypted before storage</div>
      </div>
    </div>

    <!-- BODY -->
    <div class="card-body">
      <?php if($error): ?>
      <div class="alert alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= sanitize($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>

        <!-- Personal Info -->
        <div class="section-label">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Personal Information
        </div>
        <div class="form-grid-2">
          <div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" class="form-control" placeholder="Juan dela Cruz" value="<?= sanitize($fields['full_name']??'') ?>" required></div>
          <div class="form-group"><label>Email Address <span class="req">*</span></label><input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= sanitize($fields['email']??'') ?>" required></div>
        </div>
        <div class="form-grid">
          <div class="form-group"><label>Date of Birth <span class="req">*</span></label><input type="date" name="birthdate" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= sanitize($fields['birthdate']??'') ?>" required></div>
          <div class="form-group"><label>Sex <span class="req">*</span></label>
            <select name="sex" class="form-control" required>
              <option value="">Select…</option>
              <?php foreach(['Male','Female','Other','Prefer not to say'] as $o): ?>
              <option value="<?= $o ?>" <?= ($fields['sex']??'')===$o?'selected':'' ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Blood Type</label>
            <select name="blood_type" class="form-control">
              <option value="">Unknown</option>
              <?php foreach(['A+','A−','B+','B−','AB+','AB−','O+','O−'] as $b): ?>
              <option value="<?= $b ?>" <?= ($fields['blood_type']??'')===$b?'selected':'' ?>><?= $b ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX" value="<?= sanitize($fields['phone']??'') ?>"></div>
          <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" placeholder="Name — Phone Number" value="<?= sanitize($fields['emergency_contact']??'') ?>"></div>
        </div>
        <div class="form-grid-2">
          <div class="form-group"><label>Home Address</label><input type="text" name="address" class="form-control" placeholder="Street, City, Province" value="<?= sanitize($fields['address']??'') ?>"></div>
          <div class="form-group"><label>Known Allergies</label><input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin — leave blank if none" value="<?= sanitize($fields['allergies']??'') ?>"></div>
        </div>

        <!-- Security -->
        <div class="section-label" style="margin-top:8px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Account Security
        </div>
        <div class="form-grid-2">
          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pw1" class="form-control" placeholder="Min. 8 characters" required oninput="checkStr(this.value)">
              <button type="button" class="pw-toggle" onclick="togglePw('pw1')"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
            <div class="pw-strength" id="pwStr"></div>
            <div class="pw-rules">Uppercase &bull; lowercase &bull; number &bull; special character</div>
          </div>
          <div class="form-group">
            <label>Confirm Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="confirm_pw" id="pw2" class="form-control" placeholder="Re-enter your password" required>
              <button type="button" class="pw-toggle" onclick="togglePw('pw2')"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
          </div>
        </div>

        <div class="enc-notice">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          All personal fields (name, phone, address, allergies, etc.) are encrypted with AES-256-CBC before being saved to the database. Only you can see your data.
        </div>

        <button type="submit" class="btn-submit">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
          Create Secure Account
        </button>
      </form>
    </div>

    <!-- FOOTER -->
    <div class="card-footer">
      <div class="footer-signin">Already have an account? <a href="<?= SITE_URL ?>/login.php">Sign in here</a></div>
      <div class="footer-badges">
        <div class="sec-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>AES-256 Encrypted</div>
        <div class="sec-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18" stroke-width="2.5"/></svg>Optional 2FA</div>
        <div class="sec-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Python Backup</div>
      </div>
    </div>
  </div>
</div>

<script>
function togglePw(id){var f=document.getElementById(id);f.type=f.type==='password'?'text':'password'}
function checkStr(pw){var s=0;if(pw.length>=8)s++;if(/[A-Z]/.test(pw))s++;if(/[a-z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[\W_]/.test(pw))s++;var l=['','Weak','Fair','Good','Strong','Very Strong'],c=['','#ef4444','#f59e0b','#3b82f6','#22c55e','#16a34a'];var el=document.getElementById('pwStr');if(el){el.textContent=pw?l[s]:'';el.style.color=c[s]}}
</script>
</body>
</html>
