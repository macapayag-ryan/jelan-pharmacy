<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('SITE_URL',       'http://localhost/cyberclinic');
define('SITE_NAME',      'Clinic Appointment System');
define('SITE_TAGLINE',   'Secure. Smart. Caring.');
define('DB_HOST',        'localhost');
define('DB_NAME',        'cyberclinic_secure');
define('DB_USER',        'root');
define('DB_PASS',        '');
define('APP_ENC_KEY',    'CyberClinic@2024_SecureKey32Chr!');
define('APP_ENC_CIPHER', 'AES-256-CBC');
define('SESSION_LIFETIME',   3600);
define('RL_MAX_ATTEMPTS',    5);
define('RL_WINDOW_SECONDS',  900);

// ── Gmail SMTP Settings for OTP Email ────────────────────────────
define('MAIL_FROM',     'jelanpharmacymedicallaboratory@gmail.com'); // your Gmail address
define('MAIL_PASS',     'mfpt sdkx ajbc thlf');          // Gmail App Password
define('MAIL_NAME',     'Jelan Pharmacy & Medical Laboratory');
define('OTP_EXPIRY',    3); // OTP valid for 10 minutes

// ─────────────────────────────────────────────────────────────────

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[Clinic Appointment System DB: '.$e->getMessage());
            die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Service Unavailable</h2><p>Database connection failed. Check <code>includes/config.php</code></p></div>');
        }
    }
    return $pdo;
}

function secureSessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
        session_name('CCSS');
        session_start();
        if (empty($_SESSION['_init'])) { session_regenerate_id(true); $_SESSION['_init']=true; }
        if (!empty($_SESSION['_last']) && time()-$_SESSION['_last'] > SESSION_LIFETIME) {
            session_unset(); session_destroy(); secureSessionStart(); return;
        }
        $_SESSION['_last'] = time();
        $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT']??'');
        if (!empty($_SESSION['_ua']) && $_SESSION['_ua'] !== $uaHash) {
            session_unset(); session_destroy(); secureSessionStart(); return;
        }
        $_SESSION['_ua'] = $uaHash;
    }
}
secureSessionStart();

// AES-256-CBC
function encryptField(string $plain): string {
    if ($plain==='') return '';
    $iv = random_bytes(16);
    $c  = openssl_encrypt($plain, APP_ENC_CIPHER, APP_ENC_KEY, OPENSSL_RAW_DATA, $iv);
    return $c === false ? '' : base64_encode($iv.$c);
}
function decryptField(string $enc): string {
    if ($enc==='') return '';
    $raw = base64_decode($enc, true);
    if ($raw===false || strlen($raw)<17) return '';
    $p = openssl_decrypt(substr($raw,16), APP_ENC_CIPHER, APP_ENC_KEY, OPENSSL_RAW_DATA, substr($raw,0,16));
    return $p !== false ? $p : '';
}

// CSRF
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="'.csrfToken().'">'; }
function verifyCsrf(): void {
    if (!hash_equals(csrfToken(), $_POST['csrf_token']??'')) {
        auditLog('system',null,'csrf_fail');
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Invalid Request</h2><p>Security token mismatch. Please go back and try again.</p></div>');
    }
}

// Rate Limiting
function checkRateLimit(string $email): bool {
    $db=getDB(); $key=hash('sha256',$email.':'.getClientIP());
    $db->prepare('DELETE FROM rate_limit WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)')->execute([RL_WINDOW_SECONDS]);
    $r=$db->prepare('SELECT attempts FROM rate_limit WHERE identifier=?'); $r->execute([$key]); $row=$r->fetch();
    return !($row && $row['attempts']>=RL_MAX_ATTEMPTS);
}
function recordFailedLogin(string $email): void {
    $db=getDB(); $key=hash('sha256',$email.':'.getClientIP());
    $db->prepare('INSERT INTO rate_limit (identifier,attempts,window_start) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_start<DATE_SUB(NOW(),INTERVAL ? SECOND),1,attempts+1),window_start=IF(window_start<DATE_SUB(NOW(),INTERVAL ? SECOND),NOW(),window_start)')->execute([$key,RL_WINDOW_SECONDS,RL_WINDOW_SECONDS]);
}
function clearRateLimit(string $email): void {
    $db=getDB(); $key=hash('sha256',$email.':'.getClientIP());
    $db->prepare('DELETE FROM rate_limit WHERE identifier=?')->execute([$key]);
}

// Audit Log
function auditLog(string $actorType, ?int $actorId=null, string $action='', ?string $targetType=null, ?int $targetId=null, ?string $detail=null): void {
    try {
        getDB()->prepare('INSERT INTO audit_log (actor_type,actor_id,action,target_type,target_id,detail,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)')->execute([$actorType,$actorId,$action,$targetType,$targetId,$detail,getClientIP(),substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
    } catch (Exception $e) { error_log('[CyberClinic] AuditLog: '.$e->getMessage()); }
}

// ── Gmail OTP Functions ───────────────────────────────────────────

/**
 * Generate a 6-digit OTP and save it to the database
 */
function generateOtp(int $userId, string $role): string {
    $code   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // Use SHA256+salt instead of bcrypt — faster and reliable for short-lived OTPs
    $salt   = bin2hex(random_bytes(16));
    $hash   = $salt . ':' . hash('sha256', $salt . $code);
    $expiry = date('Y-m-d H:i:s', time() + OTP_EXPIRY * 60);

    $db = getDB();
    // Delete any existing unused OTPs for this user
    $db->prepare('DELETE FROM otp_codes WHERE user_id=? AND user_type=?')->execute([$userId, $role]);
    // Insert new OTP
    $db->prepare('INSERT INTO otp_codes (user_id, user_type, otp_hash, expires_at) VALUES (?,?,?,?)')->execute([$userId, $role, $hash, $expiry]);

    error_log("[CyberClinic] OTP generated for user $userId ($role) — expires $expiry");
    return $code;
}

/**
 * Verify the OTP code entered by the user
 */
function verifyOtp(int $userId, string $role, string $code): bool {
    $db   = getDB();
    $code = preg_replace('/\s+/', '', $code);
    $code = preg_replace('/\D/', '', $code); // digits only

    error_log("[CyberClinic] verifyOtp called: userId=$userId role=$role code=$code");

    if (!ctype_digit($code) || strlen($code) !== 6) {
        error_log("[CyberClinic] OTP invalid format");
        return false;
    }

    // Use PHP time() for expiry check — avoids MySQL timezone issues
    $now = date('Y-m-d H:i:s', time());
    $s   = $db->prepare('SELECT * FROM otp_codes WHERE user_id=? AND user_type=? AND used=0 ORDER BY created_at DESC LIMIT 1');
    $s->execute([$userId, $role]);
    $row = $s->fetch();

    if (!$row) {
        error_log("[CyberClinic] No OTP record found for userId=$userId role=$role");
        return false;
    }

    // Check expiry manually using PHP
    if (strtotime($row['expires_at']) < time()) {
        error_log("[CyberClinic] OTP expired: expires_at={$row['expires_at']} now=$now");
        return false;
    }

    // Verify hash — supports both SHA256 (new) and bcrypt (old)
    $storedHash = $row['otp_hash'];
    $valid = false;

    if (strpos($storedHash, ':') !== false) {
        // New SHA256 format: salt:hash
        [$salt, $expected] = explode(':', $storedHash, 2);
        $valid = hash_equals($expected, hash('sha256', $salt . $code));
    } else {
        // Old bcrypt format fallback
        $valid = password_verify($code, $storedHash);
    }

    error_log("[CyberClinic] OTP verify result: " . ($valid ? 'VALID' : 'INVALID') . " for userId=$userId");

    if ($valid) {
        $db->prepare('UPDATE otp_codes SET used=1 WHERE id=?')->execute([$row['id']]);
        return true;
    }
    return false;
}

/**
 * Send OTP to user's Gmail via PHPMailer
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp, string $role = 'patient'): bool {
    // Try PHPMailer first, fallback to PHP mail()
    $phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';

    if (is_dir($phpmailerPath)) {
        return sendOtpViaPHPMailer($toEmail, $toName, $otp, $role);
    } else {
        // Fallback: PHP mail() function
        return sendOtpViaMail($toEmail, $toName, $otp);
    }
}

function sendOtpViaPHPMailer(string $toEmail, string $toName, string $otp, string $role): bool {
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Clinic Login OTP Code';
        $mail->Body    = buildOtpEmailHtml($otp, $toName, $role);
        $mail->AltBody = "Your CyberClinic login OTP code is: $otp\n\nThis code expires in " . OTP_EXPIRY . " minutes.\n\nIf you did not request this, please ignore this email.";
        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[CyberClinic] PHPMailer error: ' . $e->getMessage());
        return false;
    } catch (\Exception $e) {
        error_log('[CyberClinic] Mail general error: ' . $e->getMessage());
        return false;
    }
}

function sendOtpViaMail(string $toEmail, string $toName, string $otp): bool {
    // Basic PHP mail fallback (works on some servers)
    $subject = "CyberClinic Login OTP: $otp";
    $message = "Your CyberClinic OTP login code is:\n\n$otp\n\nExpires in " . OTP_EXPIRY . " minutes.\n\nIgnore if you did not request this.";
    $headers = "From: " . MAIL_NAME . " <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $result = @mail($toEmail, $subject, $message, $headers);
    if (!$result) error_log('[CyberClinic] PHP mail() failed for ' . $toEmail);
    return $result;
}

function buildOtpEmailHtml(string $otp, string $name, string $role): string {
    $expiry = OTP_EXPIRY;
    $digits = str_split($otp);
    $boxes  = '';
    foreach ($digits as $d) {
        $boxes .= "<span style='display:inline-block;width:48px;height:60px;line-height:60px;font-size:28px;font-weight:700;background:#f0f4f8;border:2px solid #1a6b8a;border-radius:8px;margin:0 4px;text-align:center;color:#0f2233'>$d</span>";
    }
    return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:520px;margin:30px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12)">
  <div style="background:linear-gradient(135deg,#0a1628 0%,#1a6b8a 100%);padding:30px 36px;text-align:center">
    <div style="width:48px;height:48px;background:linear-gradient(135deg,#1a6b8a,#0ea5c9);border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center">
      <span style="font-size:24px">🏥</span>
    </div>
    <h1 style="font-size:22px;font-weight:700;color:#fff;margin:0">CyberClinic</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:4px 0 0">Secure Medical Appointment System</p>
  </div>
  <div style="padding:36px;text-align:center">
    <h2 style="font-size:18px;color:#0f2233;margin:0 0 8px">Login Verification Code</h2>
    <p style="font-size:14px;color:#4a6480;margin:0 0 26px">Hello <strong>$name</strong>, enter this code to complete your login:</p>
    <div style="margin:0 0 24px">$boxes</div>
    <div style="background:#e8f4f8;border:1px solid #9ecfe0;border-radius:8px;padding:12px 16px;margin:0 0 20px;display:inline-block">
      <p style="font-size:13px;color:#135470;margin:0">⏱ This code expires in <strong>$expiry minutes</strong></p>
    </div>
    <p style="font-size:12px;color:#8aa5be;margin:0">If you did not try to sign in to CyberClinic, you can safely ignore this email.</p>
  </div>
  <div style="background:#f8fafb;padding:16px;text-align:center;border-top:1px solid #eef2f6">
    <p style="font-size:11px;color:#8aa5be;margin:0">© 2026 CyberClinic · Secure Medical Appointment System</p>
  </div>
</div>
</body></html>
HTML;
}

// Notifications
function createNotification(int $userId, string $type, string $title, string $message): void {
    try { getDB()->prepare('INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)')->execute([$userId,$type,$title,$message]); } catch (Exception $e) {}
}
function getUnreadCount(int $userId): int {
    $r=getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $r->execute([$userId]); return (int)$r->fetchColumn();
}

// Password & Auth
function isStrongPassword(string $pw): bool {
    return strlen($pw)>=8&&preg_match('/[A-Z]/',$pw)&&preg_match('/[a-z]/',$pw)&&preg_match('/[0-9]/',$pw)&&preg_match('/[\W_]/',$pw);
}
function isLoggedIn(): bool      { return !empty($_SESSION['user_id'])  && $_SESSION['role']==='patient'; }
function isAdminLoggedIn(): bool { return !empty($_SESSION['admin_id']) && $_SESSION['role']==='admin'; }
function requireLogin(): void  { if (!isLoggedIn())      redirect(SITE_URL.'/login.php'); }
function requireAdmin(): void  { if (!isAdminLoggedIn()) redirect(SITE_URL.'/login.php'); }
function redirect(string $url): void { header('Location: '.$url); exit; }
function sanitize($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) { $ip=trim(explode(',',$_SERVER[$k])[0]); if (filter_var($ip,FILTER_VALIDATE_IP)) return $ip; }
    }
    return '0.0.0.0';
}
function flashMessage(string $type, string $msg): void { $_SESSION['flash']=['type'=>$type,'message'=>$msg]; }
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null;
}
function statusBadge(string $s): string {
    $map=['pending'=>'badge-pending','approved'=>'badge-approved','completed'=>'badge-completed','cancelled'=>'badge-cancelled','active'=>'badge-approved'];
    return '<span class="badge '.($map[$s]??'badge').'">'.ucfirst(sanitize($s)).'</span>';
}
function computeAge(?string $dob): ?int {
    if (!$dob) return null; return (new DateTime($dob))->diff(new DateTime('today'))->y;
}
function formatFileSize(int $bytes): string {
    if ($bytes<1024) return $bytes.' B'; if ($bytes<1048576) return round($bytes/1024,1).' KB'; return round($bytes/1048576,2).' MB';
}

// OOP Classes
if (file_exists(__DIR__.'/classes.php')) require_once __DIR__.'/classes.php';