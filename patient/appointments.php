<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['cancel_id'])) {
    verifyCsrf(); $id=(int)$_POST['cancel_id'];
    $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND user_id=? AND status IN ('pending','approved')")->execute([$id,$userId]);
    auditLog('patient',$userId,'appointment_cancel','appointment',$id);
    createNotification($userId,'cancel','Appointment Cancelled','Your appointment has been cancelled successfully.');
    flashMessage('success','Appointment cancelled.'); redirect(SITE_URL.'/patient/appointments.php');
}
$filter=$_GET['status']??'all';
$sql="SELECT a.*,d.full_name AS doctor_name,d.specialty FROM appointments a JOIN doctors d ON a.doctor_id=d.id WHERE a.user_id=?";
$params=[$userId];
if ($filter!=='all'){$sql.=' AND a.status=?';$params[]=$filter;}
$sql.=' ORDER BY a.appointment_date DESC,a.appointment_time DESC';
$stmt=$db->prepare($sql); $stmt->execute($params); $appointments=$stmt->fetchAll();
$pageTitle='My Appointments — Clinic'; $activePage='appointments';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page">
  <div class="page-title">My Appointments</div>
  <p class="page-subtitle">View and manage your appointment history</p>
  <?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <a href="<?= SITE_URL ?>/patient/book.php" class="btn btn-primary btn-sm">+ Book New</a>
    <select class="filter-select" onchange="location.href='appointments.php?status='+this.value">
      <?php foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if(empty($appointments)): ?>
  <div class="card" style="text-align:center;padding:44px"><p style="color:var(--text-muted);margin-bottom:16px">No appointments found.</p><a href="<?= SITE_URL ?>/patient/book.php" class="btn btn-primary">Book Appointment</a></div>
  <?php else: ?>
  <?php foreach($appointments as $a): ?>
  <div class="appt-card">
    <div>
      <div class="appt-doctor-name"><?= sanitize($a['doctor_name']) ?> <?= statusBadge($a['status']) ?></div>
      <div style="font-size:13px;color:var(--text-muted);margin-top:2px"><?= sanitize($a['specialty']) ?></div>
      <div class="appt-meta" style="margin-top:7px">
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= $a['appointment_date'] ?></span>
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= substr($a['appointment_time'],0,5) ?></span>
      </div>
    </div>
    <?php if(in_array($a['status'],['pending','approved'])): ?>
    <form method="POST"><?= csrfField() ?><input type="hidden" name="cancel_id" value="<?= $a['id'] ?>"><button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Cancel this appointment?')">Cancel</button></form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
