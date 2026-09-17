<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $action=$_POST['action']??'';
    if ($action==='add') {
        $userId=(int)($_POST['user_id']??0); $doctorId=(int)($_POST['doctor_id']??0); $apptId=(int)($_POST['appointment_id']??0)?:null;
        $diagnosis=trim($_POST['diagnosis']??''); $treatment=trim($_POST['treatment']??''); $notes=trim($_POST['notes']??''); $recordDate=trim($_POST['record_date']??date('Y-m-d'));
        if ($userId&&$doctorId&&$diagnosis) {
            $db->prepare('INSERT INTO medical_records (user_id,doctor_id,appointment_id,diagnosis_enc,treatment_enc,notes_enc,record_date) VALUES (?,?,?,?,?,?,?)')->execute([$userId,$doctorId,$apptId,encryptField($diagnosis),encryptField($treatment),encryptField($notes),$recordDate]);
            auditLog('admin',(int)$_SESSION['admin_id'],'record_add','medical_records',(int)$db->lastInsertId());
            createNotification($userId,'record','New Medical Record Added','A new medical record has been added to your profile.');
            flashMessage('success','Medical record added.');
        } else { flashMessage('error','Patient, doctor and diagnosis are required.'); }
        redirect(SITE_URL.'/admin/records.php');
    }
    if ($action==='delete') {
        $id=(int)($_POST['id']??0);
        if ($id) { $db->prepare('DELETE FROM medical_records WHERE id=?')->execute([$id]); auditLog('admin',(int)$_SESSION['admin_id'],'record_delete','medical_records',$id); flashMessage('success','Record deleted.'); }
        redirect(SITE_URL.'/admin/records.php');
    }
}
$raw=$db->query("SELECT mr.*,u.full_name_enc,d.full_name AS doctor_name FROM medical_records mr JOIN users u ON mr.user_id=u.id JOIN doctors d ON mr.doctor_id=d.id ORDER BY mr.record_date DESC,mr.created_at DESC LIMIT 100")->fetchAll();
$records=[]; foreach($raw as $r){ $r['patient_name']=decryptField($r['full_name_enc']); $r['diagnosis']=decryptField($r['diagnosis_enc']); $r['treatment']=decryptField($r['treatment_enc']); $r['notes']=decryptField($r['notes_enc']); $records[]=$r; }
$patients=$db->query("SELECT id,full_name_enc FROM users WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
$doctors=$db->query("SELECT id,full_name,specialty FROM doctors WHERE is_active=1 ORDER BY full_name")->fetchAll();
$pageTitle='Medical Records — CyberClinic Admin'; $activePage='records';
include __DIR__.'/../includes/admin_header.php';
?>
<?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
<div class="page-header">
  <div class="page-title" style="margin-bottom:0">Medical Records <span style="font-size:14px;font-weight:400;color:var(--text-muted)">(<?= count($records) ?>)</span></div>
  <button class="btn btn-primary" onclick="showModal('addRModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Record</button>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Diagnosis</th><th>Treatment</th><th>Notes</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($records as $r): ?>
  <tr>
    <td><strong><?= sanitize($r['record_date']) ?></strong></td>
    <td><?= sanitize($r['patient_name']) ?></td>
    <td style="color:var(--primary)"><?= sanitize($r['doctor_name']) ?></td>
    <td style="max-width:200px"><div style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($r['diagnosis']) ?>"><?= sanitize($r['diagnosis']) ?></div></td>
    <td style="max-width:160px;color:var(--text-muted)"><div style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $r['treatment']?sanitize($r['treatment']):'—' ?></div></td>
    <td style="max-width:140px;color:var(--text-muted)"><div style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $r['notes']?sanitize($r['notes']):'—' ?></div></td>
    <td><form method="POST" style="display:inline" onsubmit="return confirm('Delete this record permanently?')"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Delete</button></form></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($records)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px">No medical records found.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<div id="addRModal" style="display:none;position:fixed;inset:0;background:rgba(15,34,51,.65);z-index:9999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:560px;box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h2 style="font-family:'Playfair Display',serif;font-size:19px;color:#0f2233;margin:0">Add Medical Record</h2><button onclick="hideModal('addRModal')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#4a6480;line-height:1">&times;</button></div>
  <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="add">
    <div class="form-row-2">
      <div class="form-group"><label>Patient <span class="req">*</span></label><select name="user_id" class="form-control" required><option value="">Select patient…</option><?php foreach($patients as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize(decryptField($p['full_name_enc'])) ?> (#<?= $p['id'] ?>)</option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Doctor <span class="req">*</span></label><select name="doctor_id" class="form-control" required><option value="">Select doctor…</option><?php foreach($doctors as $d): ?><option value="<?= $d['id'] ?>"><?= sanitize($d['full_name']) ?> — <?= sanitize($d['specialty']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>Record Date <span class="req">*</span></label><input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-group"><label>Appointment ID (optional)</label><input type="number" name="appointment_id" class="form-control" placeholder="Leave blank if none"></div>
    </div>
    <div class="form-group"><label>Diagnosis <span class="req">*</span></label><textarea name="diagnosis" class="form-control" rows="3" required placeholder="Enter diagnosis details…"></textarea></div>
    <div class="form-group"><label>Treatment</label><textarea name="treatment" class="form-control" rows="2" placeholder="Treatment prescribed…"></textarea></div>
    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Additional notes…"></textarea></div>
    <div style="background:var(--primary-light);border:1px solid var(--primary-mid);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--primary-dark)">&#128274; Diagnosis, treatment and notes will be AES-256 encrypted before storage.</div>
    <div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" class="btn btn-outline" onclick="hideModal('addRModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div>
  </form>
</div></div>
<script>
function showModal(id){var e=document.getElementById(id);if(e)e.style.display='flex'}
function hideModal(id){var e=document.getElementById(id);if(e)e.style.display='none'}
document.querySelectorAll('[id$="Modal"]').forEach(function(el){el.addEventListener('click',function(e){if(e.target===this)hideModal(this.id)})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('[id$="Modal"]').forEach(function(el){el.style.display='none'})});
</script>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
