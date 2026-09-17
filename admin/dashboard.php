<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$db=getDB();
$totalPatients     =$db->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
$totalDoctors      =$db->query('SELECT COUNT(*) FROM doctors WHERE is_active=1')->fetchColumn();
$totalAppts        =$db->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
$todayAppts        =$db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetchColumn();
$pendingAppts      =$db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
$completedAppts    =$db->query("SELECT COUNT(*) FROM appointments WHERE status='completed'")->fetchColumn();
$thisMonth         =$db->query("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())")->fetchColumn();
$mfaCount          =$db->query("SELECT COUNT(*) FROM users WHERE mfa_enabled=1")->fetchColumn();
$totalRecords      =$db->query('SELECT COUNT(*) FROM medical_records')->fetchColumn();
$totalRx           =$db->query("SELECT COUNT(*) FROM prescriptions WHERE status='active'")->fetchColumn();
$recentAppts=$db->query("SELECT a.*,u.full_name_enc,d.full_name AS doctor_name FROM appointments a JOIN users u ON a.user_id=u.id JOIN doctors d ON a.doctor_id=d.id ORDER BY a.created_at DESC LIMIT 8")->fetchAll();
$pageTitle='Dashboard — CyberClinic Admin'; $activePage='dashboard';
include __DIR__.'/../includes/admin_header.php';
?>
<div class="page-title">Dashboard</div>
<div class="stats-grid">
  <div class="stat-card"><div><div class="stat-label">Total Patients</div><div class="stat-value"><?= number_format($totalPatients) ?></div><div class="stat-change">Registered</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Total Appointments</div><div class="stat-value"><?= number_format($totalAppts) ?></div><div class="stat-change">All time</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">This Month</div><div class="stat-value"><?= $thisMonth ?></div><div class="stat-change"><?= date('F Y') ?></div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Today</div><div class="stat-value"><?= $todayAppts ?></div><div class="stat-change"><?= date('M j, Y') ?></div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingAppts ?></div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Completed</div><div class="stat-value"><?= number_format($completedAppts) ?></div><div class="stat-change green"><?= $totalAppts>0?round($completedAppts/$totalAppts*100).'%':0 ?> rate</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Medical Records</div><div class="stat-value"><?= number_format($totalRecords) ?></div><div class="stat-change">Encrypted</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Active Rx</div><div class="stat-value"><?= number_format($totalRx) ?></div><div class="stat-change">Prescriptions</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">Active Doctors</div><div class="stat-value"><?= $totalDoctors ?></div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div></div>
  <div class="stat-card"><div><div class="stat-label">MFA Secured</div><div class="stat-value"><?= $mfaCount ?></div><div class="stat-change green">2FA enabled</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div></div>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="font-size:17px;font-weight:700;color:var(--navy)">Recent Appointments</h2>
  <div style="display:flex;gap:10px">
    <a href="<?= SITE_URL ?>/admin/reports.php" class="btn btn-outline btn-sm">&#128202; Reports</a>
    <a href="<?= SITE_URL ?>/admin/backup.php"  class="btn btn-outline btn-sm">&#128190; Backup</a>
  </div>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach($recentAppts as $a): ?>
  <tr><td><strong><?= sanitize(decryptField($a['full_name_enc'])) ?></strong></td><td style="color:var(--primary)"><?= sanitize($a['doctor_name']) ?></td><td><?= sanitize($a['appointment_date']) ?></td><td><?= substr($a['appointment_time'],0,5) ?></td><td><?= statusBadge($a['status']) ?></td></tr>
  <?php endforeach; ?>
  <?php if(empty($recentAppts)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px">No appointments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
