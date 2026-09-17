<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $action=$_POST['action']??'';
    if ($action==='add') {
        $userId=(int)($_POST['user_id']??0); $doctorId=(int)($_POST['doctor_id']??0); $apptId=(int)($_POST['appointment_id']??0)?:null;
        $med=trim($_POST['medication']??''); $dosage=trim($_POST['dosage']??''); $instructions=trim($_POST['instructions']??''); $date=trim($_POST['prescribed_date']??date('Y-m-d')); $validUntil=trim($_POST['valid_until']??'')?:null;
        if ($userId&&$doctorId&&$med) {
            $db->prepare('INSERT INTO prescriptions (user_id,doctor_id,appointment_id,medication_enc,dosage_enc,instructions_enc,prescribed_date,valid_until) VALUES (?,?,?,?,?,?,?,?)')->execute([$userId,$doctorId,$apptId,encryptField($med),encryptField($dosage),encryptField($instructions),$date,$validUntil]);
            auditLog('admin',(int)$_SESSION['admin_id'],'prescription_add','prescriptions',(int)$db->lastInsertId());
            createNotification($userId,'prescription','New Prescription Issued','A new prescription has been added to your patient portal.');
            flashMessage('success','Prescription added.');
        } else { flashMessage('error','Patient, doctor and medication are required.'); }
        redirect(SITE_URL.'/admin/prescriptions.php');
    }
    if ($action==='update_status') {
        $id=(int)($_POST['id']??0); $status=$_POST['status']??'';
        if ($id&&in_array($status,['active','completed','cancelled'])) { $db->prepare('UPDATE prescriptions SET status=? WHERE id=?')->execute([$status,$id]); auditLog('admin',(int)$_SESSION['admin_id'],'prescription_status','prescriptions',$id,$status); flashMessage('success','Status updated.'); }
        redirect(SITE_URL.'/admin/prescriptions.php');
    }
}
$filter=$_GET['status']??'active';
$sql="SELECT p.*,u.full_name_enc,d.full_name AS doctor_name FROM prescriptions p JOIN users u ON p.user_id=u.id JOIN doctors d ON p.doctor_id=d.id WHERE 1=1";
$params=[];
if ($filter!=='all') { $sql.=' AND p.status=?'; $params[]=$filter; }
$sql.=' ORDER BY p.prescribed_date DESC LIMIT 100';
$stmt=$db->prepare($sql); $stmt->execute($params); $rxRaw=$stmt->fetchAll();
$prescriptions=[];
foreach($rxRaw as $rx){ $rx['patient_name']=decryptField($rx['full_name_enc']); $rx['medication']=decryptField($rx['medication_enc']); $rx['dosage']=decryptField($rx['dosage_enc']); $rx['instructions']=decryptField($rx['instructions_enc']); $prescriptions[]=$rx; }
$patients=$db->query("SELECT id,full_name_enc FROM users WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
$doctors=$db->query("SELECT id,full_name,specialty FROM doctors WHERE is_active=1 ORDER BY full_name")->fetchAll();
$pageTitle='Prescriptions — CyberClinic Admin'; $activePage='prescriptions';
include __DIR__.'/../includes/admin_header.php';
?>
<?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
<div class="page-header">
  <div class="page-title" style="margin-bottom:0">Prescriptions</div>
  <div style="display:flex;gap:10px;align-items:center">
    <select class="filter-select" onchange="location.href='prescriptions.php?status='+this.value"><?php foreach(['all'=>'All','active'=>'Active','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?><option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary" onclick="showModal('addRxModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Prescription</button>
  </div>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Medication</th><th>Dosage</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($prescriptions as $rx): ?>
  <tr>
    <td><?= sanitize($rx['prescribed_date']) ?></td>
    <td><strong><?= sanitize($rx['patient_name']) ?></strong></td>
    <td style="color:var(--primary)"><?= sanitize($rx['doctor_name']) ?></td>
    <td><strong><?= sanitize($rx['medication']) ?></strong></td>
    <td style="color:var(--text-muted)"><?= sanitize($rx['dosage']?:'—') ?></td>
    <td style="color:var(--text-muted)"><?= sanitize($rx['valid_until']?:'—') ?></td>
    <td><?= statusBadge($rx['status']) ?></td>
    <td>
      <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?= $rx['id'] ?>">
        <select name="status" class="filter-select" style="padding:5px 28px 5px 8px;font-size:12px" onchange="this.form.submit()">
          <option value="active"    <?= $rx['status']==='active'   ?'selected':'' ?>>Active</option>
          <option value="completed" <?= $rx['status']==='completed'?'selected':'' ?>>Completed</option>
          <option value="cancelled" <?= $rx['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($prescriptions)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">No prescriptions found.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<div id="addRxModal" style="display:none;position:fixed;inset:0;background:rgba(15,34,51,.65);z-index:9999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:540px;box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h2 style="font-family:'Playfair Display',serif;font-size:19px;color:#0f2233;margin:0">Add Prescription</h2><button onclick="hideModal('addRxModal')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#4a6480;line-height:1">&times;</button></div>
  <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="add">
    <div class="form-row-2">
      <div class="form-group"><label>Patient <span class="req">*</span></label><select name="user_id" class="form-control" required><option value="">Select…</option><?php foreach($patients as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize(decryptField($p['full_name_enc'])) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Doctor <span class="req">*</span></label><select name="doctor_id" class="form-control" required><option value="">Select…</option><?php foreach($doctors as $d): ?><option value="<?= $d['id'] ?>"><?= sanitize($d['full_name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-group"><label>Medication <span class="req">*</span></label><input type="text" name="medication" class="form-control" placeholder="e.g. Amoxicillin 500mg" required></div>
    <div class="form-row-2"><div class="form-group"><label>Dosage</label><input type="text" name="dosage" class="form-control" placeholder="e.g. 1 tablet 3x daily"></div><div class="form-group"><label>Valid Until</label><input type="date" name="valid_until" class="form-control"></div></div>
    <div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control" rows="2" placeholder="Special instructions…"></textarea></div>
    <div class="form-row-2"><div class="form-group"><label>Prescribed Date</label><input type="date" name="prescribed_date" class="form-control" value="<?= date('Y-m-d') ?>"></div><div class="form-group"><label>Appointment ID (optional)</label><input type="number" name="appointment_id" class="form-control"></div></div>
    <div style="background:var(--primary-light);border:1px solid var(--primary-mid);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--primary-dark)">&#128274; Medication details are AES-256 encrypted before storage.</div>
    <div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" class="btn btn-outline" onclick="hideModal('addRxModal')">Cancel</button><button type="submit" class="btn btn-primary">Add Prescription</button></div>
  </form>
</div></div>
<script>
function showModal(id){var e=document.getElementById(id);if(e)e.style.display='flex'}
function hideModal(id){var e=document.getElementById(id);if(e)e.style.display='none'}
document.querySelectorAll('[id$="Modal"]').forEach(function(el){el.addEventListener('click',function(e){if(e.target===this)hideModal(this.id)})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('[id$="Modal"]').forEach(function(el){el.style.display='none'})});
</script>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
