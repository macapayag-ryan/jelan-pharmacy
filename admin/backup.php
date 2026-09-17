<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $action=$_POST['action']??'';
    if ($action==='run_backup') {
        $type=$_POST['backup_type']??'manual';
        if (!in_array($type,['manual','scheduled','pre_update'])) $type='manual';
        $result=runPythonBackup($type,$_SESSION['admin_name']??'admin');
        if ($result['success']) { auditLog('admin',(int)$_SESSION['admin_id'],'backup_created',null,null,$type); flashMessage('success','Backup completed successfully!'); }
        else { flashMessage('error','Backup failed. '.substr($result['message'],0,200)); }
        redirect(SITE_URL.'/admin/backup.php');
    }
    if ($action==='delete_backup') {
        $filename=basename($_POST['filename']??'');
        if ($filename&&strpos($filename,'..')===false) {
            $path=BACKUP_DIR.$filename;
            if (file_exists($path)) { unlink($path); if(file_exists($path.'.sha256')) unlink($path.'.sha256'); auditLog('admin',(int)$_SESSION['admin_id'],'backup_deleted',null,null,$filename); flashMessage('success','Backup deleted.'); }
        }
        redirect(SITE_URL.'/admin/backup.php');
    }
}
$backupLogs=$db->query("SELECT * FROM backup_log ORDER BY created_at DESC LIMIT 20")->fetchAll();
$backupFiles=[];
if (is_dir(BACKUP_DIR)) {
    foreach (scandir(BACKUP_DIR) as $f) {
        if (strpos($f,'backup_')===0&&strpos($f,'.sha256')===false) {
            $path=BACKUP_DIR.$f; $hash='';
            if (file_exists($path.'.sha256')) { preg_match('/SHA256: ([a-f0-9]+)/i',file_get_contents($path.'.sha256'),$m); $hash=$m[1]??''; }
            $backupFiles[]=['name'=>$f,'size'=>filesize($path),'modified'=>filemtime($path),'hash'=>$hash];
        }
    }
    usort($backupFiles,function($a,$b){return $b['modified']-$a['modified'];});
}
$python=checkPythonAvailable();
$pageTitle='Backup & Recovery — CyberClinic Admin'; $activePage='backup';
include __DIR__.'/../includes/admin_header.php';
?>
<?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
<div class="page-header">
  <div class="page-title" style="margin-bottom:0">Backup &amp; Recovery</div>
  <form method="POST" style="display:flex;gap:8px;align-items:center">
    <?= csrfField() ?><input type="hidden" name="action" value="run_backup">
    <select name="backup_type" class="filter-select">
      <option value="manual">Manual Backup</option>
      <option value="pre_update">Pre-Update Backup</option>
    </select>
    <button type="submit" class="btn btn-primary" <?= !$python['available']?'disabled title="Python not found"':'' ?>>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Run Backup Now
    </button>
  </form>
</div>

<div class="python-info">
  <div class="python-logo">&#128190;</div>
  <div>
    <h3>Python Backup System</h3>
    <?php if($python['available']): ?>
    <p>&#10003; <?= sanitize($python['version']) ?> detected — Backup system is operational. AES-256 encrypted + SHA-256 integrity verified.</p>
    <?php else: ?>
    <p>&#9888; Python not found. Install Python 3: <code>sudo apt install python3</code> (Linux) or download from python.org (Windows)</p>
    <p style="font-size:12px;margin-top:4px">Then install dependencies: <code>pip install cryptography mysql-connector-python</code></p>
    <?php endif; ?>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card"><div><div class="stat-label">Backup Script</div><div class="stat-value" style="font-size:14px;margin-top:4px">backup.py</div><div class="stat-change">Python 3.6+</div></div><div class="stat-icon" style="font-size:20px">&#128190;</div></div>
  <div class="stat-card"><div><div class="stat-label">Encryption</div><div class="stat-value" style="font-size:14px;margin-top:4px">AES-256</div><div class="stat-change">Per backup file</div></div><div class="stat-icon" style="font-size:20px">&#128274;</div></div>
  <div class="stat-card"><div><div class="stat-label">Integrity</div><div class="stat-value" style="font-size:14px;margin-top:4px">SHA-256</div><div class="stat-change">Hash verified</div></div><div class="stat-icon" style="font-size:20px">&#10003;</div></div>
  <div class="stat-card"><div><div class="stat-label">Retention</div><div class="stat-value" style="font-size:14px;margin-top:4px">30 Days</div><div class="stat-change">Auto-cleanup</div></div><div class="stat-icon" style="font-size:20px">&#128197;</div></div>
</div>

<h2 style="font-size:16px;font-weight:700;color:var(--navy);margin-bottom:14px">Backup Files <span style="font-size:13px;font-weight:400;color:var(--text-muted)">(<?= count($backupFiles) ?> files)</span></h2>
<?php if(empty($backupFiles)): ?>
<div class="card" style="margin-bottom:20px"><div class="card-body" style="text-align:center;padding:36px;color:var(--text-muted)"><div style="font-size:40px;margin-bottom:12px">&#128190;</div><p>No backup files found. Click "Run Backup Now" to create your first backup.</p></div></div>
<?php else: ?>
<div class="card" style="margin-bottom:20px"><div class="table-wrap"><table>
  <thead><tr><th>Filename</th><th>Size</th><th>Created</th><th>SHA-256</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($backupFiles as $bf): ?>
  <tr>
    <td><strong style="font-family:monospace;font-size:12px"><?= sanitize($bf['name']) ?></strong></td>
    <td><?= formatFileSize($bf['size']) ?></td>
    <td style="color:var(--text-muted)"><?= date('M j, Y H:i',$bf['modified']) ?></td>
    <td style="font-family:monospace;font-size:11px;color:var(--text-muted)"><?= $bf['hash']?substr(sanitize($bf['hash']),0,16).'...':'<em>No hash</em>' ?></td>
    <td>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this backup?')">
        <?= csrfField() ?><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="filename" value="<?= sanitize($bf['name']) ?>">
        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php endif; ?>

<h2 style="font-size:16px;font-weight:700;color:var(--navy);margin-bottom:14px">Backup History</h2>
<div class="card" style="margin-bottom:20px"><div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Type</th><th>Filename</th><th>Size</th><th>Status</th><th>Created By</th></tr></thead>
  <tbody>
  <?php foreach($backupLogs as $bl): ?>
  <tr>
    <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= sanitize($bl['created_at']) ?></td>
    <td><span class="badge badge-info"><?= sanitize($bl['backup_type']) ?></span></td>
    <td style="font-family:monospace;font-size:12px"><?= sanitize($bl['filename']) ?></td>
    <td><?= $bl['file_size']?formatFileSize((int)$bl['file_size']):'—' ?></td>
    <td><?= $bl['status']==='success'?'<span class="backup-status backup-success">&#10003; Success</span>':'<span class="backup-status backup-failed">&#10005; Failed</span>' ?></td>
    <td style="color:var(--text-muted)"><?= sanitize($bl['created_by']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($backupLogs)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No backup history yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<div class="card"><div class="card-header">Manual Python Commands</div>
<div class="card-body">
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">Run these from your XAMPP server terminal:</p>
  <pre style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;font-size:12px;line-height:1.9;overflow-x:auto"><code># Manual backup
python3 scripts/backup.py --host localhost --db cyberclinic_secure --user root --outdir backup --type manual

# Security scan (last 24 hours)
python3 scripts/security_scan.py --host localhost --db cyberclinic_secure --user root --hours 24

# Install Python dependencies
pip install cryptography mysql-connector-python</code></pre>
</div></div>

<?php include __DIR__.'/../includes/admin_footer.php'; ?>
