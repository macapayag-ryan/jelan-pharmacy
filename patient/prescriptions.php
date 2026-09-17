<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];
$filter=$_GET['status']??'active';
$sql="SELECT p.*,d.full_name AS doctor_name,d.specialty FROM prescriptions p JOIN doctors d ON p.doctor_id=d.id WHERE p.user_id=?";
$params=[$userId];
if($filter!=='all'){$sql.=' AND p.status=?';$params[]=$filter;}
$sql.=' ORDER BY p.prescribed_date DESC';
$stmt=$db->prepare($sql);$stmt->execute($params);$raw=$stmt->fetchAll();
$prescriptions=[];
foreach($raw as $rx){$rx['medication']=decryptField($rx['medication_enc']);$rx['dosage']=decryptField($rx['dosage_enc']);$rx['instructions']=decryptField($rx['instructions_enc']);$prescriptions[]=$rx;}
$pageTitle='Prescriptions — Clinic'; $activePage='prescriptions';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page">
    <div class="page-title">My Prescriptions</div>
    <p class="page-subtitle">View your current and past prescriptions securely.</p>
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <select class="filter-select" onchange="location.href='prescriptions.php?status='+this.value">
            <?php foreach(['active'=>'Active','completed'=>'Completed','cancelled'=>'Cancelled','all'=>'All'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if(empty($prescriptions)): ?>
    <div class="card" style="text-align:center;padding:48px 24px"><div style="font-size:40px;margin-bottom:14px">&#128148;</div><h3 style="margin-bottom:8px;font-family:'Playfair Display',serif">No prescriptions found</h3><p style="color:var(--text-muted);font-size:14px">Prescriptions issued by your doctor will appear here.</p></div>
    <?php else: ?>
    <?php foreach($prescriptions as $rx): ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;background:var(--primary-light);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--primary)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
                <div><div style="font-size:15px;font-weight:700;color:var(--navy)"><?= sanitize($rx['medication']) ?></div><div style="font-size:12px;color:var(--text-muted)">Dr. <?= sanitize($rx['doctor_name']) ?> &middot; <?= sanitize($rx['specialty']) ?></div></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px"><?= statusBadge($rx['status']) ?><span style="font-size:13px;color:var(--text-muted)"><?= date('M j, Y',strtotime($rx['prescribed_date'])) ?></span></div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">
                <div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:6px">Dosage</div><div style="font-size:14px;color:var(--text)"><?= $rx['dosage']?sanitize($rx['dosage']):'<span style="color:var(--text-muted)">—</span>' ?></div></div>
                <div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:6px">Instructions</div><div style="font-size:14px;color:var(--text);line-height:1.6"><?= $rx['instructions']?nl2br(sanitize($rx['instructions'])):'<span style="color:var(--text-muted)">—</span>' ?></div></div>
                <div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:6px">Valid Until</div><div style="font-size:14px"><?php if($rx['valid_until']): $exp=strtotime($rx['valid_until'])<time(); ?><span style="color:<?= $exp?'var(--danger)':'var(--success)' ?>;font-weight:600"><?= date('M j, Y',strtotime($rx['valid_until'])) ?><?= $exp?' (Expired)':'' ?></span><?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?></div></div>
            </div>
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border-light);display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-light)"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>AES-256 encrypted prescription</div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
