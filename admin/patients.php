<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
$rows=$db->query("SELECT u.*,COUNT(a.id) AS total_appts,MAX(a.appointment_date) AS last_visit FROM users u LEFT JOIN appointments a ON u.id=a.user_id WHERE u.deleted_at IS NULL GROUP BY u.id ORDER BY u.id DESC")->fetchAll();
$patients=[];
foreach($rows as $r){
    $r['full_name'] =decryptField($r['full_name_enc']);
    $r['phone']     =decryptField($r['phone_enc']);
    $r['birthdate'] =decryptField($r['birthdate_enc']);
    $r['sex']       =decryptField($r['sex_enc']);
    $r['blood_type']=decryptField($r['blood_type_enc']);
    $r['allergies'] =decryptField($r['allergies_enc']);
    $patients[]=$r;
}
auditLog('admin',(int)$_SESSION['admin_id'],'viewed_patients_list');
$pageTitle='Patients — CyberClinic Admin'; $activePage='patients';
include __DIR__.'/../includes/admin_header.php';
?>
<div class="page-header"><div class="page-title" style="margin-bottom:0">Patients <span style="font-size:14px;font-weight:400;color:var(--text-muted)">(<?= count($patients) ?> registered)</span></div></div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Name</th><th>Email</th><th>Age/Sex</th><th>Blood</th><th>Allergies</th><th>Phone</th><th>Appts</th><th>Last Visit</th><th>MFA</th></tr></thead>
  <tbody>
  <?php foreach($patients as $p): ?>
  <tr>
    <td><strong><?= sanitize($p['full_name']) ?></strong></td>
    <td style="color:var(--text-muted);font-size:13px"><?= sanitize($p['email']) ?></td>
    <td><?= $p['age']??'—' ?> / <?= sanitize($p['sex']?:'—') ?></td>
    <td><?= sanitize($p['blood_type']?:'—') ?></td>
    <td><?php if($p['allergies']): ?><span style="color:var(--danger);font-size:12px">&#9888; <?= sanitize($p['allergies']) ?></span><?php else: ?><span style="color:var(--text-light)">None</span><?php endif; ?></td>
    <td style="color:var(--text-muted);font-size:13px"><?= sanitize($p['phone']?:'—') ?></td>
    <td><?= $p['total_appts'] ?></td>
    <td style="color:var(--text-muted);font-size:13px"><?= $p['last_visit']??'—' ?></td>
    <td><?= $p['mfa_enabled']?'<span class="badge badge-approved">&#10003; On</span>':'<span class="badge badge-cancelled">Off</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($patients)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px">No patients registered.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div style="margin-top:10px;font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:6px">
  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
  All PII fields are AES-256-CBC encrypted in the database.
</div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
