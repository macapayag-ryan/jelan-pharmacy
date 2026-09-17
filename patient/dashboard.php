<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];
$upcoming=$db->prepare("SELECT a.*,d.full_name AS doctor_name,d.specialty FROM appointments a JOIN doctors d ON a.doctor_id=d.id WHERE a.user_id=? AND a.appointment_date>=CURDATE() AND a.status!='cancelled' ORDER BY a.appointment_date,a.appointment_time LIMIT 5");
$upcoming->execute([$userId]); $upcoming=$upcoming->fetchAll();
$myTotal=$db->prepare('SELECT COUNT(*) FROM appointments WHERE user_id=?'); $myTotal->execute([$userId]); $myTotal=$myTotal->fetchColumn();
$myCompleted=$db->prepare("SELECT COUNT(*) FROM appointments WHERE user_id=? AND status='completed'"); $myCompleted->execute([$userId]); $myCompleted=$myCompleted->fetchColumn();
$myRecords=$db->prepare('SELECT COUNT(*) FROM medical_records WHERE user_id=?'); $myRecords->execute([$userId]); $myRecords=$myRecords->fetchColumn();
$myRx=$db->prepare("SELECT COUNT(*) FROM prescriptions WHERE user_id=? AND status='active'"); $myRx->execute([$userId]); $myRx=$myRx->fetchColumn();
$unread=getUnreadCount($userId);
$pageTitle='Dashboard — Clinic'; $activePage='dashboard';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page">
  <div class="page-title">Dashboard</div>
  <p class="page-subtitle">Welcome back, <?= sanitize($_SESSION['user_name']) ?>! &#128075;</p>
  <?php if($unread>0): ?>
  <div class="alert alert-info" style="display:flex;align-items:center;gap:10px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    You have <strong><?= $unread ?></strong> unread notification<?= $unread>1?'s':'' ?>.
    <a href="<?= SITE_URL ?>/patient/notifications.php" style="margin-left:auto;font-weight:600">View &rarr;</a>
  </div>
  <?php endif; ?>
  <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:26px">
    <div class="stat-card"><div><div class="stat-label">My Appointments</div><div class="stat-value"><?= $myTotal ?></div><div class="stat-change">Total booked</div></div><div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div></div>
    <div class="stat-card"><div><div class="stat-label">Completed</div><div class="stat-value"><?= $myCompleted ?></div><div class="stat-change green">Visits done</div></div><div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
    <div class="stat-card"><div><div class="stat-label">My Records</div><div class="stat-value"><?= $myRecords ?></div><div class="stat-change">Medical history</div></div><div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div></div>
    <div class="stat-card"><div><div class="stat-label">Active Rx</div><div class="stat-value"><?= $myRx ?></div><div class="stat-change">Prescriptions</div></div><div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div></div>
    <div class="stat-card" style="cursor:pointer" onclick="window.location='<?= SITE_URL ?>/patient/book.php'"><div><div class="stat-label">Book New</div><div class="stat-value" style="font-size:17px;margin-top:4px">+ Appointment</div></div><div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div></div>
  </div>
  <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;color:var(--navy)">Upcoming Appointments</h2>
  <?php if(empty($upcoming)): ?>
  <div class="card" style="text-align:center;padding:44px 24px">
    <div style="font-size:40px;margin-bottom:12px">&#128197;</div>
    <h3 style="margin-bottom:8px;font-family:'Playfair Display',serif">No upcoming appointments</h3>
    <p style="color:var(--text-muted);margin-bottom:20px;font-size:14px">Book with one of our specialists today.</p>
    <a href="<?= SITE_URL ?>/patient/book.php" class="btn btn-primary">Book Appointment</a>
  </div>
  <?php else: ?>
  <?php foreach($upcoming as $a): ?>
  <div class="appt-card">
    <div>
      <div class="appt-doctor-name"><?= sanitize($a['doctor_name']) ?> <?= statusBadge($a['status']) ?></div>
      <div class="appt-meta">
        <span><?= sanitize($a['specialty']) ?></span>
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= $a['appointment_date'] ?></span>
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= substr($a['appointment_time'],0,5) ?></span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <div style="margin-top:12px;display:flex;gap:10px">
    <a href="<?= SITE_URL ?>/patient/appointments.php" class="btn btn-outline">View All</a>
    <a href="<?= SITE_URL ?>/patient/book.php" class="btn btn-primary">+ Book New</a>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
