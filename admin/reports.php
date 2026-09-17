<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
$view=$_GET['view']??'monthly'; $year=(int)($_GET['year']??date('Y'));
$totalPatients=$db->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
$totalDoctors=$db->query('SELECT COUNT(*) FROM doctors WHERE is_active=1')->fetchColumn();
$totalAppts=$db->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
$todayAppts=$db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetchColumn();
$pendingAppts=$db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
$completedAppts=$db->query("SELECT COUNT(*) FROM appointments WHERE status='completed'")->fetchColumn();
$cancelledAppts=$db->query("SELECT COUNT(*) FROM appointments WHERE status='cancelled'")->fetchColumn();
$mfaCount=$db->query("SELECT COUNT(*) FROM users WHERE mfa_enabled=1")->fetchColumn();
$totalRecords=$db->query('SELECT COUNT(*) FROM medical_records')->fetchColumn();
$activeRx=$db->query("SELECT COUNT(*) FROM prescriptions WHERE status='active'")->fetchColumn();
$thisMonth=$db->query("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())")->fetchColumn();
$mStmt=$db->prepare("SELECT MONTH(appointment_date) AS m,COUNT(*) AS total,SUM(status='completed') AS completed,SUM(status='cancelled') AS cancelled,SUM(status='pending') AS pending FROM appointments WHERE YEAR(appointment_date)=? GROUP BY MONTH(appointment_date) ORDER BY m");
$mStmt->execute([$year]); $monthly=$mStmt->fetchAll();
$monthLabels=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$mTotal=$mCompleted=$mCancelled=$mPending=array_fill(0,12,0);
foreach($monthly as $r){$i=(int)$r['m']-1;$mTotal[$i]=(int)$r['total'];$mCompleted[$i]=(int)$r['completed'];$mCancelled[$i]=(int)$r['cancelled'];$mPending[$i]=(int)$r['pending'];}
$npStmt=$db->prepare("SELECT MONTH(created_at) AS m,COUNT(*) AS cnt FROM users WHERE YEAR(created_at)=? GROUP BY MONTH(created_at)");
$npStmt->execute([$year]); $newPatients=array_fill(0,12,0);
foreach($npStmt->fetchAll() as $r) $newPatients[(int)$r['m']-1]=(int)$r['cnt'];
$weeklyData=$db->query("SELECT YEARWEEK(appointment_date,1) AS yw,MIN(appointment_date) AS week_start,COUNT(*) AS total,SUM(status='completed') AS completed,SUM(status='cancelled') AS cancelled FROM appointments WHERE appointment_date>=DATE_SUB(CURDATE(),INTERVAL 8 WEEK) GROUP BY YEARWEEK(appointment_date,1) ORDER BY yw")->fetchAll();
$weekLabels=$weekTotal=$weekCompleted=$weekCancelled=[];
foreach($weeklyData as $r){$weekLabels[]=date('M d',strtotime($r['week_start']));$weekTotal[]=(int)$r['total'];$weekCompleted[]=(int)$r['completed'];$weekCancelled[]=(int)$r['cancelled'];}
$yearlyData=array_reverse($db->query("SELECT YEAR(appointment_date) AS yr,COUNT(*) AS total,SUM(status='completed') AS completed,COUNT(DISTINCT user_id) AS unique_patients FROM appointments GROUP BY YEAR(appointment_date) ORDER BY yr DESC LIMIT 5")->fetchAll());
$yrLabels=$yrTotal=$yrCompleted=$yrPatients=[];
foreach($yearlyData as $r){$yrLabels[]=$r['yr'];$yrTotal[]=(int)$r['total'];$yrCompleted[]=(int)$r['completed'];$yrPatients[]=(int)$r['unique_patients'];}
$dStmt=$db->prepare("SELECT d.full_name,d.specialty,COUNT(a.id) AS total,SUM(a.status='completed') AS completed,SUM(a.status='cancelled') AS cancelled FROM doctors d LEFT JOIN appointments a ON d.id=a.doctor_id AND YEAR(a.appointment_date)=? WHERE d.is_active=1 GROUP BY d.id ORDER BY total DESC");
$dStmt->execute([$year]); $dRows=$dStmt->fetchAll(); $dNames=[]; $dTotals=[];
foreach($dRows as $r){$dNames[]=$r['full_name'];$dTotals[]=(int)$r['total'];}
$sStmt=$db->prepare("SELECT d.specialty,COUNT(a.id) AS total,SUM(a.status='completed') AS completed,SUM(a.status='cancelled') AS cancelled FROM appointments a JOIN doctors d ON a.doctor_id=d.id WHERE YEAR(a.appointment_date)=? GROUP BY d.specialty ORDER BY total DESC");
$sStmt->execute([$year]); $sRows=$sStmt->fetchAll(); $sTotal=array_sum(array_column($sRows,'total'));
$mdStmt=$db->prepare("SELECT MONTH(appointment_date) AS m,MONTHNAME(appointment_date) AS mname,COUNT(*) AS total,SUM(status='pending') AS pending,SUM(status='approved') AS approved,SUM(status='completed') AS completed,SUM(status='cancelled') AS cancelled,COUNT(DISTINCT user_id) AS patients,COUNT(DISTINCT doctor_id) AS doctors_used FROM appointments WHERE YEAR(appointment_date)=? GROUP BY MONTH(appointment_date),MONTHNAME(appointment_date) ORDER BY m");
$mdStmt->execute([$year]); $mDetails=$mdStmt->fetchAll();
$statusDist=['Pending'=>(int)$pendingAppts,'Approved'=>(int)$db->query("SELECT COUNT(*) FROM appointments WHERE status='approved'")->fetchColumn(),'Completed'=>(int)$completedAppts,'Cancelled'=>(int)$cancelledAppts];
$pageTitle='Reports & Analytics — CyberClinic Admin'; $activePage='reports';
include __DIR__.'/../includes/admin_header.php';
?>
<style>
.report-table th{background:var(--bg);padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);border-bottom:2px solid var(--border)}
.report-table td{padding:10px 14px;border-bottom:1px solid var(--border-light);font-size:13px}
.report-table tr:hover td{background:#f8fafc}
</style>
<div class="page-header"><div class="page-title" style="margin-bottom:0">Reports &amp; Analytics</div><div style="font-size:13px;color:var(--text-muted)">Data as of <?= date('F j, Y') ?></div></div>
<div class="kpi-grid">
    <div class="kpi accent"><div class="kpi-label">Total Patients</div><div class="kpi-value"><?= number_format($totalPatients) ?></div><div class="kpi-sub">Registered</div></div>
    <div class="kpi accent"><div class="kpi-label">Total Appointments</div><div class="kpi-value"><?= number_format($totalAppts) ?></div><div class="kpi-sub">All time</div></div>
    <div class="kpi accent"><div class="kpi-label">This Month</div><div class="kpi-value"><?= $thisMonth ?></div><div class="kpi-sub"><?= date('F Y') ?></div></div>
    <div class="kpi green"><div class="kpi-label">Completed</div><div class="kpi-value"><?= number_format($completedAppts) ?></div><div class="kpi-sub"><?= $totalAppts>0?round($completedAppts/$totalAppts*100).'%':0 ?> rate</div></div>
    <div class="kpi orange"><div class="kpi-label">Pending</div><div class="kpi-value"><?= number_format($pendingAppts) ?></div></div>
    <div class="kpi red"><div class="kpi-label">Cancelled</div><div class="kpi-value"><?= number_format($cancelledAppts) ?></div></div>
    <div class="kpi teal"><div class="kpi-label">Medical Records</div><div class="kpi-value"><?= number_format($totalRecords) ?></div><div class="kpi-sub">Encrypted</div></div>
    <div class="kpi teal"><div class="kpi-label">Active Rx</div><div class="kpi-value"><?= number_format($activeRx) ?></div></div>
    <div class="kpi"><div class="kpi-label">Active Doctors</div><div class="kpi-value"><?= $totalDoctors ?></div></div>
    <div class="kpi green"><div class="kpi-label">MFA Secured</div><div class="kpi-value"><?= $mfaCount ?></div><div class="kpi-sub">2FA enabled</div></div>
</div>
<div class="report-tabs">
    <?php foreach(['monthly'=>'Monthly','weekly'=>'Weekly','yearly'=>'Yearly','doctors'=>'By Doctor','specialty'=>'By Specialty'] as $k=>$l): ?>
    <a href="?view=<?= $k ?>&year=<?= $year ?>" class="rtab <?= $view===$k?'active':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
</div>
<?php if(in_array($view,['monthly','doctors','specialty'])): ?>
<div class="year-nav">
    <a href="?view=<?= $view ?>&year=<?= $year-1 ?>">&larr; <?= $year-1 ?></a>
    <span><?= $year ?></span>
    <a href="?view=<?= $view ?>&year=<?= $year+1 ?>"><?= $year+1 ?> &rarr;</a>
</div>
<?php endif; ?>
<?php if($view==='monthly'): ?>
<div class="charts-grid">
    <div class="chart-card full"><div class="chart-title">Monthly Appointments — <?= $year ?></div><canvas id="mChart" height="80"></canvas></div>
    <div class="chart-card"><div class="chart-title">New Patients — <?= $year ?></div><canvas id="npChart" height="140"></canvas></div>
    <div class="chart-card"><div class="chart-title">Status Distribution</div><canvas id="sdChart" height="140"></canvas></div>
</div>
<div class="section-title">Monthly Breakdown — <?= $year ?></div>
<div class="card"><div class="table-wrap"><table class="report-table">
    <thead><tr><th>Month</th><th>Total</th><th>Pending</th><th>Approved</th><th>Completed</th><th>Cancelled</th><th>Patients</th><th>Doctors</th><th>Completion %</th></tr></thead>
    <tbody>
    <?php $gt=$gc=$gx=$gp=0; foreach($mDetails as $r){$gt+=$r['total'];$gc+=$r['completed'];$gx+=$r['cancelled'];$gp+=$r['patients'];$rate=$r['total']>0?round($r['completed']/$r['total']*100):0; ?>
    <tr><td><strong><?= sanitize($r['mname']) ?></strong></td><td><?= $r['total'] ?></td><td><?= $r['pending'] ?></td><td><?= $r['approved'] ?></td><td style="color:#16a34a;font-weight:600"><?= $r['completed'] ?></td><td style="color:var(--danger)"><?= $r['cancelled'] ?></td><td><?= $r['patients'] ?></td><td><?= $r['doctors_used'] ?></td><td><span class="pct-bar-wrap"><span class="pct-bar" style="width:<?= $rate ?>%"></span></span> <span style="font-size:12px;margin-left:4px"><?= $rate ?>%</span></td></tr>
    <?php } ?>
    <?php if(empty($mDetails)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:28px">No data for <?= $year ?>.</td></tr><?php endif; ?>
    <tr style="background:var(--bg);font-weight:700"><td>TOTAL</td><td><?= $gt ?></td><td colspan="2"></td><td style="color:#16a34a"><?= $gc ?></td><td style="color:var(--danger)"><?= $gx ?></td><td><?= $gp ?></td><td colspan="2"></td></tr>
    </tbody>
</table></div></div>
<script>
new Chart(document.getElementById('mChart'),{type:'bar',data:{labels:<?= json_encode($monthLabels) ?>,datasets:[{label:'Total',data:<?= json_encode($mTotal) ?>,backgroundColor:'rgba(26,107,138,.15)',borderColor:'#1a6b8a',borderWidth:2,type:'line',fill:true,tension:.4,pointRadius:4,pointBackgroundColor:'#1a6b8a'},{label:'Completed',data:<?= json_encode($mCompleted) ?>,backgroundColor:'rgba(22,163,74,.7)',borderRadius:4},{label:'Cancelled',data:<?= json_encode($mCancelled) ?>,backgroundColor:'rgba(220,38,38,.7)',borderRadius:4},{label:'Pending',data:<?= json_encode($mPending) ?>,backgroundColor:'rgba(217,119,6,.7)',borderRadius:4}]},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
new Chart(document.getElementById('npChart'),{type:'bar',data:{labels:<?= json_encode($monthLabels) ?>,datasets:[{label:'New Patients',data:<?= json_encode($newPatients) ?>,backgroundColor:'rgba(26,107,138,.7)',borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
new Chart(document.getElementById('sdChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($statusDist)) ?>,datasets:[{data:<?= json_encode(array_values($statusDist)) ?>,backgroundColor:['#d97706','#1a6b8a','#16a34a','#dc2626'],borderWidth:2}]},options:{responsive:true,plugins:{legend:{position:'right'}}}});
</script>
<?php elseif($view==='weekly'): ?>
<div class="charts-grid"><div class="chart-card full"><div class="chart-title">Weekly Appointments — Last 8 Weeks</div><canvas id="wChart" height="80"></canvas></div></div>
<div class="section-title">Weekly Summary</div>
<div class="card"><div class="table-wrap"><table class="report-table">
    <thead><tr><th>Week Starting</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Completion Rate</th></tr></thead>
    <tbody>
    <?php foreach($weeklyData as $r){$rate=$r['total']>0?round($r['completed']/$r['total']*100):0; ?>
    <tr><td><strong><?= date('F j, Y',strtotime($r['week_start'])) ?></strong></td><td><?= $r['total'] ?></td><td style="color:#16a34a;font-weight:600"><?= $r['completed'] ?></td><td style="color:var(--danger)"><?= $r['cancelled'] ?></td><td><span class="pct-bar-wrap"><span class="pct-bar" style="width:<?= $rate ?>%"></span></span> <span style="font-size:12px;margin-left:4px"><?= $rate ?>%</span></td></tr>
    <?php } ?>
    <?php if(empty($weeklyData)): ?><tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted)">No data available.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<script>new Chart(document.getElementById('wChart'),{type:'line',data:{labels:<?= json_encode($weekLabels) ?>,datasets:[{label:'Total',data:<?= json_encode($weekTotal) ?>,borderColor:'#1a6b8a',backgroundColor:'rgba(26,107,138,.1)',fill:true,tension:.4,pointRadius:5,pointBackgroundColor:'#1a6b8a'},{label:'Completed',data:<?= json_encode($weekCompleted) ?>,borderColor:'#16a34a',backgroundColor:'transparent',tension:.4,pointRadius:4},{label:'Cancelled',data:<?= json_encode($weekCancelled) ?>,borderColor:'#dc2626',backgroundColor:'transparent',tension:.4,pointRadius:4}]},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});</script>
<?php elseif($view==='yearly'): ?>
<div class="charts-grid">
    <div class="chart-card full"><div class="chart-title">Yearly Appointments — Last 5 Years</div><canvas id="yChart" height="80"></canvas></div>
    <div class="chart-card"><div class="chart-title">Unique Patients per Year</div><canvas id="ypChart" height="140"></canvas></div>
    <div class="chart-card"><div class="chart-title">Completion Rate per Year</div><canvas id="yrChart" height="140"></canvas></div>
</div>
<div class="section-title">Yearly Summary</div>
<div class="card"><div class="table-wrap"><table class="report-table">
    <thead><tr><th>Year</th><th>Total</th><th>Completed</th><th>Unique Patients</th><th>Completion Rate</th></tr></thead>
    <tbody>
    <?php foreach($yearlyData as $r){$rate=$r['total']>0?round($r['completed']/$r['total']*100):0; ?>
    <tr><td><strong><?= $r['yr'] ?></strong></td><td><?= number_format($r['total']) ?></td><td style="color:#16a34a;font-weight:600"><?= number_format($r['completed']) ?></td><td><?= number_format($r['unique_patients']) ?></td><td><span class="pct-bar-wrap"><span class="pct-bar" style="width:<?= $rate ?>%"></span></span> <span style="font-size:12px;margin-left:4px"><?= $rate ?>%</span></td></tr>
    <?php } ?>
    <?php if(empty($yearlyData)): ?><tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted)">No data.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?php $yrRates=array_map(function($r){return $r['total']>0?round($r['completed']/$r['total']*100):0;},$yearlyData); ?>
<script>
new Chart(document.getElementById('yChart'),{type:'bar',data:{labels:<?= json_encode($yrLabels) ?>,datasets:[{label:'Total',data:<?= json_encode($yrTotal) ?>,backgroundColor:'rgba(26,107,138,.7)',borderRadius:6},{label:'Completed',data:<?= json_encode($yrCompleted) ?>,backgroundColor:'rgba(22,163,74,.7)',borderRadius:6}]},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
new Chart(document.getElementById('ypChart'),{type:'line',data:{labels:<?= json_encode($yrLabels) ?>,datasets:[{label:'Unique Patients',data:<?= json_encode($yrPatients) ?>,borderColor:'#1a6b8a',backgroundColor:'rgba(26,107,138,.1)',fill:true,tension:.4,pointRadius:5,pointBackgroundColor:'#1a6b8a'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
new Chart(document.getElementById('yrChart'),{type:'bar',data:{labels:<?= json_encode($yrLabels) ?>,datasets:[{label:'Completion %',data:<?= json_encode($yrRates) ?>,backgroundColor:'rgba(22,163,74,.7)',borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,max:100,ticks:{callback:function(v){return v+'%'}}}}}});
</script>
<?php elseif($view==='doctors'): ?>
<div class="charts-grid"><div class="chart-card full"><div class="chart-title">Appointments by Doctor — <?= $year ?></div><canvas id="drChart" height="70"></canvas></div></div>
<div class="section-title">Doctor Performance — <?= $year ?></div>
<div class="card"><div class="table-wrap"><table class="report-table">
    <thead><tr><th>#</th><th>Doctor</th><th>Specialty</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Completion Rate</th></tr></thead>
    <tbody>
    <?php foreach($dRows as $i=>$r){$rate=$r['total']>0?round($r['completed']/$r['total']*100):0; ?>
    <tr><td><span style="display:inline-flex;width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;font-size:11px;font-weight:700;align-items:center;justify-content:center"><?= $i+1 ?></span></td><td><strong><?= sanitize($r['full_name']) ?></strong></td><td style="color:var(--primary)"><?= sanitize($r['specialty']) ?></td><td><?= $r['total'] ?></td><td style="color:#16a34a;font-weight:600"><?= $r['completed'] ?></td><td style="color:var(--danger)"><?= $r['cancelled'] ?></td><td><span class="pct-bar-wrap"><span class="pct-bar" style="width:<?= $rate ?>%"></span></span> <span style="font-size:12px;margin-left:4px"><?= $rate ?>%</span></td></tr>
    <?php } ?>
    </tbody>
</table></div></div>
<script>new Chart(document.getElementById('drChart'),{type:'bar',data:{labels:<?= json_encode($dNames) ?>,datasets:[{label:'Appointments',data:<?= json_encode($dTotals) ?>,backgroundColor:['rgba(26,107,138,.8)','rgba(22,163,74,.8)','rgba(59,130,246,.8)','rgba(217,119,6,.8)','rgba(168,85,247,.8)','rgba(236,72,153,.8)','rgba(14,165,201,.8)','rgba(234,88,12,.8)'],borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});</script>
<?php elseif($view==='specialty'): ?>
<div class="charts-grid">
    <div class="chart-card"><div class="chart-title">Specialty Distribution — <?= $year ?></div><canvas id="spPie" height="180"></canvas></div>
    <div class="chart-card"><div class="chart-title">Volume by Specialty — <?= $year ?></div><canvas id="spBar" height="180"></canvas></div>
</div>
<div class="section-title">Specialty Breakdown — <?= $year ?></div>
<div class="card"><div class="table-wrap"><table class="report-table">
    <thead><tr><th>Specialty</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Share</th></tr></thead>
    <tbody>
    <?php foreach($sRows as $r){$share=$sTotal>0?round($r['total']/$sTotal*100):0; ?>
    <tr><td><strong><?= sanitize($r['specialty']) ?></strong></td><td><?= $r['total'] ?></td><td style="color:#16a34a;font-weight:600"><?= $r['completed'] ?></td><td style="color:var(--danger)"><?= $r['cancelled'] ?></td><td><span class="pct-bar-wrap" style="width:100px"><span class="pct-bar" style="width:<?= $share ?>%"></span></span> <span style="font-size:12px;margin-left:4px"><?= $share ?>%</span></td></tr>
    <?php } ?>
    <?php if(empty($sRows)): ?><tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted)">No data for <?= $year ?>.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?php $sNames=array_column($sRows,'specialty'); $sVals=array_map('intval',array_column($sRows,'total')); $sColors=['rgba(26,107,138,.8)','rgba(22,163,74,.8)','rgba(59,130,246,.8)','rgba(217,119,6,.8)','rgba(168,85,247,.8)','rgba(236,72,153,.8)','rgba(14,165,201,.8)','rgba(234,88,12,.8)']; ?>
<script>
new Chart(document.getElementById('spPie'),{type:'doughnut',data:{labels:<?= json_encode($sNames) ?>,datasets:[{data:<?= json_encode($sVals) ?>,backgroundColor:<?= json_encode($sColors) ?>,borderWidth:2}]},options:{responsive:true,plugins:{legend:{position:'right'}}}});
new Chart(document.getElementById('spBar'),{type:'bar',data:{labels:<?= json_encode($sNames) ?>,datasets:[{label:'Appointments',data:<?= json_encode($sVals) ?>,backgroundColor:<?= json_encode($sColors) ?>,borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
</script>
<?php endif; ?>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
