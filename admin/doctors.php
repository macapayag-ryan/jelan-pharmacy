<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $action=$_POST['action']??'';
    if ($action==='add') {
        $name=trim($_POST['full_name']??''); $spec=trim($_POST['specialty']??''); $bio=trim($_POST['bio']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??''); $lic=trim($_POST['license_number']??''); $avail=trim($_POST['availability']??'Mon-Fri');
        if ($name&&$spec&&$email) {
            $words=array_filter(explode(' ',$name)); $initials=''; foreach($words as $w) $initials.=strtoupper(substr($w,0,1)); $initials=substr($initials,0,2);
            $db->prepare('INSERT INTO doctors (full_name,specialty,bio,email,phone,initials,license_number,availability) VALUES (?,?,?,?,?,?,?,?)')->execute([$name,$spec,$bio,$email,$phone,$initials,$lic,$avail]);
            auditLog('admin',(int)$_SESSION['admin_id'],'doctor_add','doctors',(int)$db->lastInsertId()); flashMessage('success','Doctor added.');
        } else { flashMessage('error','Name, specialty and email are required.'); }
    } elseif ($action==='edit') {
        $id=(int)($_POST['id']??0); $name=trim($_POST['full_name']??''); $spec=trim($_POST['specialty']??''); $bio=trim($_POST['bio']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??''); $lic=trim($_POST['license_number']??''); $avail=trim($_POST['availability']??'Mon-Fri');
        if ($id&&$name&&$spec&&$email) { $db->prepare('UPDATE doctors SET full_name=?,specialty=?,bio=?,email=?,phone=?,license_number=?,availability=? WHERE id=?')->execute([$name,$spec,$bio,$email,$phone,$lic,$avail,$id]); auditLog('admin',(int)$_SESSION['admin_id'],'doctor_edit','doctors',$id); flashMessage('success','Doctor updated.'); }
        else { flashMessage('error','Required fields missing.'); }
    } elseif ($action==='delete') {
        $id=(int)($_POST['id']??0);
        if ($id) { $db->prepare('DELETE FROM appointments WHERE doctor_id=?')->execute([$id]); $db->prepare('DELETE FROM doctors WHERE id=?')->execute([$id]); auditLog('admin',(int)$_SESSION['admin_id'],'doctor_delete','doctors',$id); flashMessage('success','Doctor removed.'); }
    }
    redirect(SITE_URL.'/admin/doctors.php');
}
$doctors=$db->query('SELECT * FROM doctors ORDER BY full_name')->fetchAll();
$pageTitle='Doctors — CyberClinic Admin'; $activePage='doctors';
include __DIR__.'/../includes/admin_header.php';
?>
<?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
<div class="page-header">
  <div class="page-title" style="margin-bottom:0">Doctors <span style="font-size:14px;font-weight:400;color:var(--text-muted)">(<?= count($doctors) ?>)</span></div>
  <button class="btn btn-primary" onclick="showModal('addModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Doctor</button>
</div>
<?php if(empty($doctors)): ?>
<div style="text-align:center;padding:60px;color:var(--text-muted)"><p style="margin-bottom:16px">No doctors added yet.</p><button class="btn btn-primary" onclick="showModal('addModal')">Add first doctor</button></div>
<?php else: ?>
<div class="doctors-grid">
<?php foreach($doctors as $d): ?>
<div class="doctor-card">
  <div class="doctor-card-header">
    <div class="doctor-info">
      <div class="doctor-avatar"><?= sanitize($d['initials']??'??') ?></div>
      <div><div class="doctor-name"><?= sanitize($d['full_name']) ?></div><div class="doctor-spec"><?= sanitize($d['specialty']) ?></div><div class="doctor-avail">&#128197; <?= sanitize($d['availability']??'') ?></div></div>
    </div>
    <div class="doctor-actions">
      <button class="icon-btn" title="Edit" onclick="openEdit('<?= (int)$d['id'] ?>','<?= addslashes(sanitize($d['full_name'])) ?>','<?= addslashes(sanitize($d['specialty'])) ?>','<?= addslashes(sanitize($d['bio']??'')) ?>','<?= addslashes(sanitize($d['email'])) ?>','<?= addslashes(sanitize($d['phone']??'')) ?>','<?= addslashes(sanitize($d['license_number']??'')) ?>','<?= addslashes(sanitize($d['availability']??'Mon-Fri')) ?>')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </button>
      <button class="icon-btn delete" title="Delete" onclick="openDelete('<?= (int)$d['id'] ?>','<?= addslashes(sanitize($d['full_name'])) ?>')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
      </button>
    </div>
  </div>
  <?php if(!empty($d['bio'])): ?><p class="doctor-bio"><?= sanitize($d['bio']) ?></p><?php endif; ?>
  <div class="doctor-contact"><?= sanitize($d['email']) ?><?php if(!empty($d['phone'])): ?> &middot; <?= sanitize($d['phone']) ?><?php endif; ?><?php if(!empty($d['license_number'])): ?><br><small style="color:var(--text-light)">PRC: <?= sanitize($d['license_number']) ?></small><?php endif; ?></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(15,34,51,.65);z-index:9999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:520px;box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h2 style="font-family:'Playfair Display',serif;font-size:19px;color:#0f2233;margin:0">Add Doctor</h2><button onclick="hideModal('addModal')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#4a6480;line-height:1">&times;</button></div>
  <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="add">
    <div class="form-row-2"><div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" class="form-control" required></div><div class="form-group"><label>Specialty <span class="req">*</span></label><input type="text" name="specialty" class="form-control" required></div></div>
    <div class="form-group"><label>Bio</label><textarea name="bio" class="form-control" rows="2"></textarea></div>
    <div class="form-row-2"><div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" class="form-control" required></div><div class="form-group"><label>Phone</label><input type="tel" name="phone" class="form-control"></div></div>
    <div class="form-row-2"><div class="form-group"><label>License No.</label><input type="text" name="license_number" class="form-control" placeholder="PRC-XXXX-XXX"></div><div class="form-group"><label>Availability</label><input type="text" name="availability" class="form-control" value="Mon-Fri"></div></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px"><button type="button" class="btn btn-outline" onclick="hideModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Add Doctor</button></div>
  </form>
</div></div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(15,34,51,.65);z-index:9999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:520px;box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h2 style="font-family:'Playfair Display',serif;font-size:19px;color:#0f2233;margin:0">Edit Doctor</h2><button onclick="hideModal('editModal')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#4a6480;line-height:1">&times;</button></div>
  <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
    <div class="form-row-2"><div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" id="editName" class="form-control" required></div><div class="form-group"><label>Specialty <span class="req">*</span></label><input type="text" name="specialty" id="editSpec" class="form-control" required></div></div>
    <div class="form-group"><label>Bio</label><textarea name="bio" id="editBio" class="form-control" rows="2"></textarea></div>
    <div class="form-row-2"><div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" id="editEmail" class="form-control" required></div><div class="form-group"><label>Phone</label><input type="tel" name="phone" id="editPhone" class="form-control"></div></div>
    <div class="form-row-2"><div class="form-group"><label>License No.</label><input type="text" name="license_number" id="editLicense" class="form-control"></div><div class="form-group"><label>Availability</label><input type="text" name="availability" id="editAvail" class="form-control"></div></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px"><button type="button" class="btn btn-outline" onclick="hideModal('editModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
  </form>
</div></div>

<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(15,34,51,.65);z-index:9999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:400px;box-shadow:0 24px 64px rgba(0,0,0,.2)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 style="font-family:'Playfair Display',serif;font-size:19px;color:#0f2233;margin:0">Remove Doctor</h2><button onclick="hideModal('deleteModal')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#4a6480;line-height:1">&times;</button></div>
  <p style="color:var(--text-muted);font-size:14px;margin-bottom:6px">Remove <strong id="deleteName"></strong>?</p>
  <p style="color:var(--danger);font-size:13px;margin-bottom:22px">&#9888; This will also delete all their appointments.</p>
  <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteId">
    <div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" class="btn btn-outline" onclick="hideModal('deleteModal')">Cancel</button><button type="submit" class="btn btn-danger">Yes, Remove</button></div>
  </form>
</div></div>

<script>
function showModal(id){var e=document.getElementById(id);if(e)e.style.display='flex'}
function hideModal(id){var e=document.getElementById(id);if(e)e.style.display='none'}
function openEdit(id,name,spec,bio,email,phone,lic,avail){
  document.getElementById('editId').value=id; document.getElementById('editName').value=name; document.getElementById('editSpec').value=spec;
  document.getElementById('editBio').value=bio; document.getElementById('editEmail').value=email; document.getElementById('editPhone').value=phone;
  document.getElementById('editLicense').value=lic; document.getElementById('editAvail').value=avail; showModal('editModal');
}
function openDelete(id,name){document.getElementById('deleteId').value=id;document.getElementById('deleteName').textContent=name;showModal('deleteModal')}
['addModal','editModal','deleteModal'].forEach(function(id){var e=document.getElementById(id);if(e)e.addEventListener('click',function(ev){if(ev.target===this)hideModal(id)})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')['addModal','editModal','deleteModal'].forEach(hideModal)});
</script>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
