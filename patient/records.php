<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];
$records=$db->prepare("SELECT mr.*,d.full_name AS doctor_name,d.specialty FROM medical_records mr JOIN doctors d ON mr.doctor_id=d.id WHERE mr.user_id=? ORDER BY mr.record_date DESC,mr.created_at DESC");
$records->execute([$userId]); $raw=$records->fetchAll();
$myRecords=[];
foreach($raw as $r){$r['diagnosis']=decryptField($r['diagnosis_enc']);$r['treatment']=decryptField($r['treatment_enc']);$r['notes']=decryptField($r['notes_enc']);$myRecords[]=$r;}
auditLog('patient',$userId,'viewed_medical_records');
$pageTitle='My Medical Records — Clinic'; $activePage='records';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page">
    <div class="page-title">My Medical Records</div>
    <p class="page-subtitle">Your encrypted medical history — visible only to you and your care team.</p>
    <?php if(empty($myRecords)): ?>
    <div class="card" style="text-align:center;padding:48px 24px"><div style="font-size:40px;margin-bottom:14px">&#128196;</div><h3 style="margin-bottom:8px;font-family:'Playfair Display',serif">No medical records yet</h3><p style="color:var(--text-muted);font-size:14px">Your records will appear here after your appointments.</p></div>
    <?php else: ?>
    <?php foreach($myRecords as $r): ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header">
            <div><span style="font-family:'Playfair Display',serif;font-size:16px"><?= sanitize($r['doctor_name']) ?></span><span style="margin-left:10px;font-size:12px;color:var(--text-muted);font-weight:400"><?= sanitize($r['specialty']) ?></span></div>
            <span style="font-size:13px;color:var(--text-muted);font-weight:400"><?= date('F j, Y',strtotime($r['record_date'])) ?></span>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">
                <div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:6px">Diagnosis</div><div style="font-size:14px;color:var(--text);line-height:1.6"><?= nl2br(sanitize($r['diagnosis'])) ?></div></div>
                <div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:6px">Treatment</div><div style="font-size:14px;color:var(--text);line-height:1.6"><?= $r['treatment']?nl2br(sanitize($r['treatment'])):'<span style="color:var(--text-muted)">—</span>' ?></div></div>
                <div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:6px">Notes</div><div style="font-size:14px;color:var(--text);line-height:1.6"><?= $r['notes']?nl2br(sanitize($r['notes'])):'<span style="color:var(--text-muted)">—</span>' ?></div></div>
            </div>
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border-light);display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-light)"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>AES-256-CBC encrypted</div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
