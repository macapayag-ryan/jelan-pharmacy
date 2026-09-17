<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];

function fetchUser(PDO $db, int $id): array {
    $s=$db->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$id]); $u=$s->fetch(); if(!$u) return [];
    $u['full_name']         =decryptField((string)($u['full_name_enc']??''));
    $u['phone']             =decryptField((string)($u['phone_enc']??''));
    $u['birthdate']         =decryptField((string)($u['birthdate_enc']??''));
    $u['sex']               =decryptField((string)($u['sex_enc']??''));
    $u['address']           =decryptField((string)($u['address_enc']??''));
    $u['emergency_contact'] =decryptField((string)($u['emergency_contact_enc']??''));
    $u['blood_type']        =decryptField((string)($u['blood_type_enc']??''));
    $u['allergies']         =decryptField((string)($u['allergies_enc']??''));
    return $u;
}

$tab=$_GET['tab']??'profile';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $action=$_POST['action']??'';

    if ($action==='update_profile') {
        $name=trim($_POST['full_name']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??'');
        $birthdate=trim($_POST['birthdate']??''); $sex=trim($_POST['sex']??''); $address=trim($_POST['address']??'');
        $emergency=trim($_POST['emergency_contact']??''); $blood=trim($_POST['blood_type']??''); $allergies=trim($_POST['allergies']??'');
        if (empty($name)||empty($email)||empty($birthdate)||empty($sex)) { flashMessage('error','Name, email, DOB and sex are required.'); }
        elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { flashMessage('error','Invalid email address.'); }
        else {
            $dup=$db->prepare('SELECT id FROM users WHERE email=? AND id!=?'); $dup->execute([$email,$userId]);
            if ($dup->fetch()) { flashMessage('error','That email is already in use.'); }
            else {
                $age=computeAge($birthdate);
                $db->prepare('UPDATE users SET email=?,full_name_enc=?,phone_enc=?,birthdate_enc=?,sex_enc=?,address_enc=?,emergency_contact_enc=?,blood_type_enc=?,allergies_enc=?,age=? WHERE id=?')
                   ->execute([$email,encryptField($name),encryptField($phone),encryptField($birthdate),encryptField($sex),encryptField($address),encryptField($emergency),encryptField($blood),encryptField($allergies),$age,$userId]);
                $_SESSION['user_name']=$name; $_SESSION['user_email']=$email;
                auditLog('patient',$userId,'profile_update');
                flashMessage('success','Profile updated successfully.');
            }
        }
        redirect(SITE_URL.'/patient/profile.php?tab=profile');
    }

    if ($action==='change_password') {
        $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm_password']??'';
        $u=fetchUser($db,$userId);
        if (!password_verify($cur,$u['password'])) { flashMessage('error','Current password is incorrect.'); }
        elseif ($new!==$conf) { flashMessage('error','New passwords do not match.'); }
        elseif (!isStrongPassword($new)) { flashMessage('error','Password must be at least 8 chars with uppercase, lowercase, number and special character.'); }
        else {
            $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>10]),$userId]);
            auditLog('patient',$userId,'password_change');
            flashMessage('success','Password changed successfully.');
        }
        redirect(SITE_URL.'/patient/profile.php?tab=security');
    }

    if ($action==='mfa_generate') { $_SESSION['pending_mfa_secret']=generateTotpSecret(); redirect(SITE_URL.'/patient/profile.php?tab=security'); }

    if ($action==='mfa_confirm') {
        $code=str_replace(' ','',trim($_POST['mfa_code']??'')); $secret=$_SESSION['pending_mfa_secret']??'';
        if ($secret&&verifyTotp($secret,$code)) {
            $backups=generateBackupCodes();
            $db->prepare('UPDATE users SET mfa_secret=?,mfa_enabled=1,mfa_backup_codes=? WHERE id=?')->execute([encryptField($secret),json_encode($backups['hashed']),$userId]);
            $_SESSION['mfa_backup_plain']=$backups['plain'];
            unset($_SESSION['pending_mfa_secret']);
            auditLog('patient',$userId,'mfa_enabled');
            flashMessage('success','Two-factor authentication is now active!');
        } else { flashMessage('error','Invalid code. Make sure your phone clock is synced and try again.'); }
        redirect(SITE_URL.'/patient/profile.php?tab=security');
    }

    if ($action==='mfa_disable') {
        $pw=$_POST['confirm_pw']??''; $u=fetchUser($db,$userId);
        if (!password_verify($pw,$u['password'])) { flashMessage('error','Password incorrect.'); }
        else {
            $db->prepare('UPDATE users SET mfa_secret=NULL,mfa_enabled=0,mfa_backup_codes=NULL WHERE id=?')->execute([$userId]);
            auditLog('patient',$userId,'mfa_disabled');
            flashMessage('success','Two-factor authentication disabled.');
        }
        redirect(SITE_URL.'/patient/profile.php?tab=security');
    }

    redirect(SITE_URL.'/patient/profile.php?tab='.$tab);
}

$user=fetchUser($db,$userId);
$pendingSecret=$_SESSION['pending_mfa_secret']??null;
$backupPlain=$_SESSION['mfa_backup_plain']??null;
if($backupPlain) unset($_SESSION['mfa_backup_plain']);
$mfaBroken=!empty($user['mfa_enabled'])&&(empty($user['mfa_secret'])||strlen($user['mfa_secret'])<80);
$flash=getFlash();
$pageTitle='Profile — CyberClinic'; $activePage='profile';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page" style="max-width:780px">
    <div class="page-title">Profile &amp; Security</div>
    <p class="page-subtitle">Manage your personal information and account security.</p>

    <?php if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>

    <?php if($backupPlain): ?>
    <div class="alert alert-info" style="display:block;line-height:2.4">
        <strong>&#9888; Save your 8 backup codes NOW — they will NOT be shown again!</strong><br>
        <code style="font-size:13px;letter-spacing:2px;background:rgba(0,0,0,.05);padding:4px 8px;border-radius:4px"><?= implode(' &nbsp;&nbsp; ',array_map('sanitize',$backupPlain)) ?></code>
    </div>
    <?php endif; ?>

    <div class="tab-nav">
        <a href="?tab=profile"  class="tab-link <?= $tab==='profile' ?'active':'' ?>">Personal Info</a>
        <a href="?tab=security" class="tab-link <?= $tab==='security'?'active':'' ?>">Security</a>
    </div>

    <?php if($tab==='profile'): ?>
    <div class="card"><div class="card-body">
        <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="update_profile">
            <div class="form-section-label">Basic Information</div>
            <div class="form-row-2">
                <div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required></div>
                <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" class="form-control" value="<?= sanitize($user['email']) ?>" required></div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label>Date of Birth <span class="req">*</span></label><input type="date" name="birthdate" class="form-control" value="<?= sanitize($user['birthdate']) ?>" max="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Sex <span class="req">*</span></label>
                    <select name="sex" class="form-control" required>
                        <?php foreach(['Male','Female','Other','Prefer not to say'] as $o): ?>
                        <option value="<?= $o ?>" <?= $user['sex']===$o?'selected':'' ?>><?= $o ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Blood Type</label>
                    <select name="blood_type" class="form-control">
                        <option value="">Unknown</option>
                        <?php foreach(['A+','A−','B+','B−','AB+','AB−','O+','O−'] as $b): ?>
                        <option value="<?= $b ?>" <?= $user['blood_type']===$b?'selected':'' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>Phone</label><input type="tel" name="phone" class="form-control" value="<?= sanitize($user['phone']) ?>"></div>
                <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" value="<?= sanitize($user['emergency_contact']) ?>"></div>
            </div>
            <div class="form-group"><label>Home Address</label><input type="text" name="address" class="form-control" value="<?= sanitize($user['address']) ?>"></div>
            <div class="form-group"><label>Known Allergies</label><input type="text" name="allergies" class="form-control" value="<?= sanitize($user['allergies']) ?>" placeholder="e.g. Penicillin — leave blank if none"></div>
            <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--primary-light);border:1px solid var(--primary-mid);border-radius:var(--radius-sm);margin-bottom:16px;font-size:12px;color:var(--primary-dark)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                All personal information is AES-256 encrypted before being saved.
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div></div>
    <div class="card" style="margin-top:14px"><div class="card-body">
        <p style="font-size:13px;color:var(--text-muted)">Member since <?= date('F j, Y',strtotime($user['created_at'])) ?> &nbsp;&middot;&nbsp; Age: <strong><?= $user['age']??'—' ?></strong></p>
    </div></div>

    <?php elseif($tab==='security'): ?>
    <div class="card" style="margin-bottom:18px">
        <div class="card-header">Change Password</div>
        <div class="card-body">
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="change_password">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="form-row-2">
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" oninput="checkStr(this.value)" required><div class="pw-strength" id="pwStr"></div></div>
                    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                </div>
                <small class="pw-rules">Min 8 chars &bull; uppercase &bull; lowercase &bull; number &bull; special character</small><br><br>
                <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            Two-Factor Authentication (TOTP)
            <?php if(!empty($user['mfa_enabled'])): ?><span class="badge badge-approved">&#10003; Enabled</span>
            <?php else: ?><span class="badge badge-pending">Disabled</span><?php endif; ?>
        </div>
        <div class="card-body">
        <?php if($mfaBroken): ?>
            <div class="alert alert-error"><strong>&#9888; Your 2FA setup is broken</strong> — the stored secret was truncated. Reset it below to fix.</div>
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="mfa_disable">
                <div class="form-group"><label>Enter your password to reset 2FA</label><input type="password" name="confirm_pw" class="form-control" style="max-width:300px" required></div>
                <button type="submit" class="btn btn-primary btn-sm">Reset &amp; Re-setup 2FA</button>
            </form>
        <?php elseif(!empty($user['mfa_enabled'])&&!$pendingSecret): ?>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:18px">Your account is protected. A 6-digit code from Google Authenticator or Authy is required every time you sign in.</p>
            <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--bg);border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:18px">
                <div style="font-size:28px">&#128241;</div>
                <div><div style="font-size:13px;font-weight:600;margin-bottom:2px">Google Authenticator or Authy</div><div style="font-size:12px;color:var(--text-muted)">Open your app when signing in.</div></div>
                <span class="badge badge-approved" style="margin-left:auto">Active</span>
            </div>
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="mfa_disable">
                <div class="form-group"><label>Enter your password to disable 2FA</label><input type="password" name="confirm_pw" class="form-control" style="max-width:300px" required placeholder="Your current password"></div>
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Disable 2FA? Your account will be less secure.')">Disable 2FA</button>
            </form>
        <?php elseif($pendingSecret): ?>
            <p style="font-size:14px;margin-bottom:18px"><strong>Step 1:</strong> Scan the QR code with <strong>Google Authenticator</strong> or <strong>Authy</strong>.<br><strong>Step 2:</strong> Enter the 6-digit code to confirm.</p>
            <?php $uri=totpUri($pendingSecret,$user['email']); ?>
            <div style="text-align:center;margin-bottom:22px">
                <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=<?= urlencode($uri) ?>&choe=UTF-8" alt="TOTP QR Code" style="border:6px solid white;border-radius:12px;box-shadow:var(--shadow-md)">
                <p style="font-size:12px;color:var(--text-muted);margin-top:12px">Can't scan? Enter this key manually:<br><code style="background:var(--bg);padding:5px 12px;border-radius:6px;font-size:12px;letter-spacing:3px;display:inline-block;margin-top:6px"><?= sanitize($pendingSecret) ?></code></p>
            </div>
            <form method="POST" style="max-width:340px"><?= csrfField() ?><input type="hidden" name="action" value="mfa_confirm">
                <div class="form-group"><label>6-Digit Authenticator Code</label>
                    <input type="text" name="mfa_code" class="form-control mfa-input" placeholder="000 000" maxlength="7" autofocus inputmode="numeric" autocomplete="one-time-code">
                </div>
                <div style="display:flex;gap:10px"><button type="submit" class="btn btn-primary">Activate 2FA</button><a href="?tab=security" class="btn btn-outline">Cancel</a></div>
            </form>
        <?php else: ?>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:18px">Add an extra layer of security. Once enabled, you need a 6-digit code from your phone every time you sign in. You will also receive 8 one-time backup codes.</p>
            <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--bg);border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:18px">
                <div style="font-size:28px">&#128241;</div>
                <div><div style="font-size:13px;font-weight:600;margin-bottom:2px">Google Authenticator or Authy</div><div style="font-size:12px;color:var(--text-muted)">Install on your phone, then click Set up 2FA.</div></div>
            </div>
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="mfa_generate">
                <button type="submit" class="btn btn-primary btn-sm">Set up 2FA</button>
            </form>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
function checkStr(pw){var s=0;if(pw.length>=8)s++;if(/[A-Z]/.test(pw))s++;if(/[a-z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[\W_]/.test(pw))s++;var l=['','Weak','Fair','Good','Strong','Very Strong'],c=['','#ef4444','#f59e0b','#3b82f6','#22c55e','#16a34a'];var el=document.getElementById('pwStr');if(el){el.textContent=pw?l[s]:'';el.style.color=c[s];}}
var mi=document.querySelector('.mfa-input');
if(mi)mi.addEventListener('input',function(){var v=this.value.replace(/\D/g,'');if(v.length>3)v=v.slice(0,3)+' '+v.slice(3,6);this.value=v;});
</script>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
