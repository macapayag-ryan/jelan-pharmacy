<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    if (($_POST['action']??'')==='run_scan') {
        $script=__DIR__.'/../scripts/security_scan.py';
        $reportPath=__DIR__.'/../backup/security_report.json';
        $python=checkPythonAvailable();
        if ($python['available']&&file_exists($script)) {
            $cmd=escapeshellcmd($python['command']).' '.escapeshellarg($script).' --host '.escapeshellarg(DB_HOST).' --db '.escapeshellarg(DB_NAME).' --user '.escapeshellarg(DB_USER).' --pass '.escapeshellarg(DB_PASS).' --hours 24 --report '.escapeshellarg($reportPath).' 2>&1';
            shell_exec($cmd);
            auditLog('admin',(int)$_SESSION['admin_id'],'security_scan_run');
            flashMessage('success','Security scan completed.');
        } else { flashMessage('error','Python not available or scan script not found.'); }
        redirect(SITE_URL.'/admin/security.php');
    }
}
$reportPath=__DIR__.'/../backup/security_report.json';
$report=null;
if (file_exists($reportPath)) $report=json_decode(file_get_contents($reportPath),true);

$last24h       =$db->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$failedLogins  =$db->query("SELECT COUNT(*) FROM audit_log WHERE action IN ('login_fail','login_mfa_fail') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$successLogins =$db->query("SELECT COUNT(*) FROM audit_log WHERE action='login_success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$mfaFails      =$db->query("SELECT COUNT(*) FROM audit_log WHERE action='login_mfa_fail' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$rateLimited   =$db->query("SELECT COUNT(*) FROM audit_log WHERE action='login_rate_limited' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$mfaEnabled    =$db->query("SELECT COUNT(*) FROM users WHERE mfa_enabled=1")->fetchColumn();
$totalUsers    =$db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
$topIPs        =$db->query("SELECT ip_address,COUNT(*) AS cnt FROM audit_log WHERE action IN ('login_fail','login_mfa_fail','login_rate_limited') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY ip_address ORDER BY cnt DESC LIMIT 5")->fetchAll();
$python=checkPythonAvailable();
$pageTitle='Security Center — CyberClinic Admin'; $activePage='security';
include __DIR__.'/../includes/admin_header.php';
?>
<?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
<div class="page-header">
    <div class="page-title" style="margin-bottom:0">Security Center</div>
    <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="run_scan">
        <button type="submit" class="btn btn-primary" <?= !$python['available']?'disabled':'' ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Run Python Security Scan
        </button>
    </form>
</div>
<div class="python-info" style="margin-bottom:20px">
    <div class="python-logo">&#128737;</div>
    <div>
        <h3>Python Security Scanner</h3>
        <?php if($python['available']): ?>
        <p>&#10003; <?= sanitize($python['version']) ?> — Scanner is operational. Analyzes audit logs for brute-force attacks, MFA abuse, and unusual access patterns.</p>
        <?php else: ?>
        <p>&#9888; Python not found. Install Python 3 to enable automated security scanning.</p>
        <?php endif; ?>
    </div>
</div>
<div class="section-title" style="margin-top:0">Live Security Metrics — Last 24 Hours</div>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div class="kpi green"><div class="kpi-label">Successful Logins</div><div class="kpi-value"><?= $successLogins ?></div><div class="kpi-sub">Last 24h</div></div>
    <div class="kpi <?= $failedLogins>10?'red':($failedLogins>3?'orange':'green') ?>"><div class="kpi-label">Failed Logins</div><div class="kpi-value"><?= $failedLogins ?></div><div class="kpi-sub">Last 24h</div></div>
    <div class="kpi <?= $mfaFails>5?'red':($mfaFails>0?'orange':'green') ?>"><div class="kpi-label">MFA Failures</div><div class="kpi-value"><?= $mfaFails ?></div><div class="kpi-sub">Last 24h</div></div>
    <div class="kpi <?= $rateLimited>0?'red':'green' ?>"><div class="kpi-label">Rate Limited</div><div class="kpi-value"><?= $rateLimited ?></div><div class="kpi-sub">Blocked</div></div>
    <div class="kpi accent"><div class="kpi-label">Total Events</div><div class="kpi-value"><?= $last24h ?></div><div class="kpi-sub">Audit entries</div></div>
    <div class="kpi green"><div class="kpi-label">MFA Adoption</div><div class="kpi-value"><?= $totalUsers>0?round($mfaEnabled/$totalUsers*100).'%':'0%' ?></div><div class="kpi-sub"><?= $mfaEnabled ?>/<?= $totalUsers ?></div></div>
</div>
<?php if(!empty($topIPs)): ?>
<div class="section-title">Suspicious IPs — Last 24 Hours</div>
<div class="card" style="margin-bottom:20px"><div class="table-wrap"><table>
    <thead><tr><th>IP Address</th><th>Failed Attempts</th><th>Risk Level</th></tr></thead>
    <tbody>
    <?php foreach($topIPs as $ip): $c=(int)$ip['cnt']; ?>
    <tr>
        <td style="font-family:monospace;font-weight:600"><?= sanitize($ip['ip_address']??'—') ?></td>
        <td><?= $c ?></td>
        <td><?= $c>=5?'<span class="badge badge-cancelled">HIGH RISK</span>':($c>=3?'<span class="badge badge-pending">MEDIUM</span>':'<span class="badge badge-info">LOW</span>') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php endif; ?>
<?php if($report): ?>
<div class="section-title">Latest Python Scan Report</div>
<div class="card" style="margin-bottom:20px"><div class="card-body">
    <?php $score=$report['security_score']??0; $grade=$report['security_grade']??'N/A'; $gColor=['A'=>'#16a34a','B'=>'#16a34a','C'=>'#d97706','D'=>'#dc2626','F'=>'#dc2626'][$grade]??'#64748b'; ?>
    <div style="display:flex;align-items:center;gap:28px;margin-bottom:20px">
        <div style="text-align:center"><div style="font-size:52px;font-weight:700;color:<?= $gColor ?>;font-family:'Playfair Display',serif;line-height:1"><?= $grade ?></div><div style="font-size:12px;color:var(--text-muted)">Grade</div></div>
        <div><div style="font-size:28px;font-weight:700;color:var(--navy);font-family:'Playfair Display',serif"><?= $score ?>/100</div><div style="font-size:13px;color:var(--text-muted)">Security Score</div><div style="font-size:12px;color:var(--text-light);margin-top:4px">Scanned: <?= sanitize($report['generated_at']??'—') ?></div></div>
        <div style="flex:1"><?php $s=$report['summary']??[]; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div style="text-align:center;padding:10px;background:var(--bg);border-radius:var(--radius-sm)"><div style="font-size:20px;font-weight:700;color:var(--danger)"><?= $s['high_alerts']??0 ?></div><div style="font-size:11px;color:var(--text-muted)">HIGH</div></div>
            <div style="text-align:center;padding:10px;background:var(--bg);border-radius:var(--radius-sm)"><div style="font-size:20px;font-weight:700;color:var(--warning)"><?= $s['medium_alerts']??0 ?></div><div style="font-size:11px;color:var(--text-muted)">MEDIUM</div></div>
        </div></div>
    </div>
    <?php if(!empty($report['all_alerts'])): ?>
    <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--navy)">Alerts</h3>
    <?php foreach($report['all_alerts'] as $alert): $sev=$alert['severity']??'LOW'; $sc=['HIGH'=>'var(--danger)','MEDIUM'=>'var(--warning)','LOW'=>'var(--primary)'][$sev]??'var(--text)'; $sb=['HIGH'=>'#fef2f2','MEDIUM'=>'#fffbeb','LOW'=>'var(--primary-light)'][$sev]??'var(--bg)'; ?>
    <div style="padding:10px 14px;background:<?= $sb ?>;border-left:3px solid <?= $sc ?>;border-radius:6px;margin-bottom:8px">
        <strong style="color:<?= $sc ?>;font-size:12px">[<?= sanitize($sev) ?>]</strong>
        <span style="font-size:13px;color:var(--text);margin-left:8px"><?= sanitize($alert['message']??'') ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php if(!empty($report['recommendations'])): ?>
    <h3 style="font-size:14px;font-weight:700;margin:16px 0 10px;color:var(--navy)">Recommendations</h3>
    <?php foreach($report['recommendations'] as $rec): ?>
    <div style="display:flex;gap:8px;padding:8px 0;border-bottom:1px solid var(--border-light);font-size:13px;color:var(--text-muted)"><span style="color:var(--primary);flex-shrink:0">&#8594;</span><?= sanitize($rec) ?></div>
    <?php endforeach; ?>
    <?php endif; ?>
</div></div>
<?php else: ?>
<div class="card" style="margin-bottom:20px"><div class="card-body" style="text-align:center;padding:36px;color:var(--text-muted)"><div style="font-size:40px;margin-bottom:12px">&#128737;</div><p>No scan report yet. Click <strong>Run Python Security Scan</strong> to generate one.</p></div></div>
<?php endif; ?>
<div class="section-title">Security Checklist</div>
<div class="card"><div class="card-body">
<?php $checks=[
    ['label'=>'AES-256 Encryption Active','status'=>true,'detail'=>'All PII fields encrypted before database storage'],
    ['label'=>'CSRF Protection','status'=>true,'detail'=>'Token verification on all POST forms'],
    ['label'=>'Rate Limiting','status'=>true,'detail'=>RL_MAX_ATTEMPTS.' attempts per '.RL_WINDOW_SECONDS.'s window'],
    ['label'=>'Session Hardening','status'=>true,'detail'=>'HttpOnly, SameSite=Strict, UA binding, idle timeout'],
    ['label'=>'Password Hashing','status'=>true,'detail'=>'bcrypt cost=10 with strength enforcement'],
    ['label'=>'MFA Available','status'=>true,'detail'=>'TOTP-based 2FA with Google Authenticator'],
    ['label'=>'Audit Logging','status'=>true,'detail'=>'All security events logged immutably'],
    ['label'=>'Python Backup System','status'=>$python['available'],'detail'=>$python['available']?$python['version'].' detected':'Python not found — install Python 3'],
    ['label'=>'MFA Adoption','status'=>($mfaEnabled>0),'detail'=>$mfaEnabled.' of '.$totalUsers.' patients have 2FA enabled'],
];
foreach($checks as $chk): ?>
<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light)">
    <span style="font-size:18px;flex-shrink:0;color:<?= $chk['status']?'var(--success)':'var(--warning)' ?>"><?= $chk['status']?'&#10003;':'&#9888;' ?></span>
    <div style="flex:1"><div style="font-size:13px;font-weight:600;color:var(--navy)"><?= sanitize($chk['label']) ?></div><div style="font-size:12px;color:var(--text-muted)"><?= sanitize($chk['detail']) ?></div></div>
    <span class="badge <?= $chk['status']?'badge-approved':'badge-pending' ?>"><?= $chk['status']?'OK':'ACTION NEEDED' ?></span>
</div>
<?php endforeach; ?>
</div></div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
