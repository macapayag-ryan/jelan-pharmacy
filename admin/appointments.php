<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $id=(int)($_POST['id']??0); $action=$_POST['action']??'';
    if ($id&&in_array($action,['approve','reject','complete'])) {
        $map=['approve'=>'approved','reject'=>'cancelled','complete'=>'completed'];
        $db->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$map[$action],$id]);
        auditLog('admin',(int)$_SESSION['admin_id'],'appointment_'.$action,'appointment',$id);
        $appt=$db->prepare('SELECT user_id,appointment_date,appointment_time FROM appointments WHERE id=?'); $appt->execute([$id]); $apptRow=$appt->fetch();
        if ($apptRow) {
            $msgs=['approve'=>'Your appointment on '.$apptRow['appointment_date'].' at '.substr($apptRow['appointment_time'],0,5).' has been approved.','reject'=>'Your appointment on '.$apptRow['appointment_date'].' has been cancelled.','complete'=>'Your appointment on '.$apptRow['appointment_date'].' has been marked as completed.'];
            $titles=['approve'=>'Appointment Approved','reject'=>'Appointment Cancelled','complete'=>'Appointment Completed'];
            createNotification((int)$apptRow['user_id'],$action,$titles[$action],$msgs[$action]);
        }
        flashMessage('success','Appointment updated.');
    }
    redirect(SITE_URL.'/admin/appointments.php');
}
$search=trim($_GET['search']??''); $filter=$_GET['status']??'all';
$sql="SELECT a.*,u.full_name_enc,d.full_name AS doctor_name,d.specialty FROM appointments a JOIN users u ON a.user_id=u.id JOIN doctors d ON a.doctor_id=d.id WHERE 1=1";
$params=[];
if ($filter!=='all') { $sql.=' AND a.status=?'; $params[]=$filter; }
$sql.=' ORDER BY a.appointment_date DESC,a.appointment_time DESC';
$stmt=$db->prepare($sql); $stmt->execute($params); $raw=$stmt->fetchAll();
$appointments=[];
foreach ($raw as $a) { $a['patient_name']=decryptField($a['full_name_enc']); if ($search&&stripos($a['patient_name'],$search)===false&&stripos($a['doctor_name'],$search)===false&&stripos($a['specialty'],$search)===false) continue; $appointments[]=$a; }
$pageTitle='Appointments — CyberClinic Admin'; $activePage='appointments';
include __DIR__.'/../includes/admin_header.php';
?>
<?php $flash=getFlash(); if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
<div class="page-header">
  <div class="page-title" style="margin-bottom:0">Appointments <span style="font-size:14px;font-weight:400;color:var(--text-muted)">(<?= count($appointments) ?>)</span></div>
  <div class="page-header-right">
    <form method="GET" style="display:flex;gap:10px;align-items:center">
      <div class="search-bar"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="search" placeholder="Search patient / doctor…" value="<?= sanitize($search) ?>"></div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <?php foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Patient</th><th>Doctor</th><th>Specialty</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($appointments as $a): ?>
  <tr>
    <td><strong><?= sanitize($a['patient_name']) ?></strong></td>
    <td><?= sanitize($a['doctor_name']) ?></td>
    <td><span class="badge badge-info"><?= sanitize($a['specialty']) ?></span></td>
    <td><?= sanitize($a['appointment_date']) ?></td>
    <td><?= substr($a['appointment_time'],0,5) ?></td>
    <td><?= statusBadge($a['status']) ?></td>
    <td>
      <?php if($a['status']==='pending'): ?>
      <div class="action-btns">
        <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="action" value="approve"><button type="submit" class="action-approve" title="Approve">&#10003;</button></form>
        <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="action" value="reject"><button type="submit" class="action-reject" title="Reject">&#10005;</button></form>
      </div>
      <?php elseif($a['status']==='approved'): ?>
      <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="action" value="complete"><button type="submit" class="btn btn-sm btn-outline" style="font-size:12px">Mark Done</button></form>
      <?php else: ?><span style="color:var(--text-muted);font-size:13px">—</span><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($appointments)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px">No appointments found.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
