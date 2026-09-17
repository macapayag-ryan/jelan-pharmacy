<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf(); $action=$_POST['action']??'';
    if ($action==='select_doctor'){$d=(int)($_POST['doctor_id']??0);if($d){$_SESSION['book_doctor_id']=$d;$_SESSION['book_step']=2;}redirect(SITE_URL.'/patient/book.php');}
    if ($action==='select_datetime'){$date=trim($_POST['date']??'');$time=trim($_POST['time']??'');if($date&&$time){$_SESSION['book_date']=$date;$_SESSION['book_time']=$time;$_SESSION['book_step']=3;}redirect(SITE_URL.'/patient/book.php');}
    if ($action==='confirm'){
        $docId=(int)($_SESSION['book_doctor_id']??0);$date=$_SESSION['book_date']??'';$time=$_SESSION['book_time']??'';
        if($docId&&$date&&$time){
            $chk=$db->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status!='cancelled'");$chk->execute([$docId,$date,$time]);
            if($chk->fetch()){flashMessage('error','This slot is already taken. Please choose another.');$_SESSION['book_step']=2;}
            else{
                $db->prepare("INSERT INTO appointments (user_id,doctor_id,appointment_date,appointment_time) VALUES (?,?,?,?)")->execute([$userId,$docId,$date,$time]);
                $newId=(int)$db->lastInsertId();
                auditLog('patient',$userId,'appointment_booked','appointment',$newId);
                createNotification($userId,'booking','Appointment Submitted','Your appointment request has been submitted and is pending approval.');
                unset($_SESSION['book_step'],$_SESSION['book_doctor_id'],$_SESSION['book_date'],$_SESSION['book_time']);
                $_SESSION['book_success']=true;
            }
        }
        redirect(SITE_URL.'/patient/book.php');
    }
    if ($action==='back'){$_SESSION['book_step']=max(1,(int)($_SESSION['book_step']??1)-1);redirect(SITE_URL.'/patient/book.php');}
    if ($action==='reset'){unset($_SESSION['book_step'],$_SESSION['book_doctor_id'],$_SESSION['book_date'],$_SESSION['book_time'],$_SESSION['book_success']);redirect(SITE_URL.'/patient/book.php');}
    redirect(SITE_URL.'/patient/book.php');
}
$step=(int)($_SESSION['book_step']??1);
$success=!empty($_SESSION['book_success']);if($success)unset($_SESSION['book_success']);
$doctors=$db->query('SELECT * FROM doctors WHERE is_active=1 ORDER BY specialty,full_name')->fetchAll();
$selectedDoctor=null;$takenSlots=[];
if($step>=2&&!empty($_SESSION['book_doctor_id'])){
    $ds=$db->prepare('SELECT * FROM doctors WHERE id=? AND is_active=1');$ds->execute([(int)$_SESSION['book_doctor_id']]);$selectedDoctor=$ds->fetch()?:null;
    if(!$selectedDoctor){unset($_SESSION['book_step'],$_SESSION['book_doctor_id'],$_SESSION['book_date'],$_SESSION['book_time']);flashMessage('error','Doctor no longer available.');redirect(SITE_URL.'/patient/book.php');}
    $bs=$db->prepare("SELECT appointment_date,appointment_time FROM appointments WHERE doctor_id=? AND appointment_date>=CURDATE() AND status!='cancelled'");$bs->execute([(int)$_SESSION['book_doctor_id']]);
    foreach($bs->fetchAll() as $row) $takenSlots[$row['appointment_date']][]=substr($row['appointment_time'],0,5);
}
$timeSlots=['8:00','09:00','09:30','10:00','10:30','11:00','14:00','14:30','15:00','15:30','16:00'];
$pageTitle='Book Appointment — CyberClinic';$activePage='book';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page" style="max-width:860px">
  <h1 class="page-title">Book an Appointment</h1>
  <p class="page-subtitle">Select a doctor, choose your date and time, then confirm.</p>
  <?php $flash=getFlash();if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div><?php endif; ?>
  <?php if($success): ?>
  <div class="card"><div class="card-body"><div class="success-screen">
    <div class="success-icon"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
    <h2>Booking Submitted!</h2>
    <p>Your appointment is pending admin approval. Check My Appointments for updates.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="reset"><button type="submit" class="btn btn-primary btn-lg">Book Another</button></form>
      <a href="<?= SITE_URL ?>/patient/appointments.php" class="btn btn-outline btn-lg">My Appointments</a>
    </div>
  </div></div></div>
  <?php else: ?>
  <div class="steps" style="margin-bottom:28px">
    <div class="step <?= $step>1?'done':($step===1?'active':'') ?>"><div class="step-num"><?= $step>1?'&#10003;':'1' ?></div><span class="step-label">Doctor</span></div>
    <div class="step-line <?= $step>1?'done':'' ?>"></div>
    <div class="step <?= $step>2?'done':($step===2?'active':'') ?>"><div class="step-num"><?= $step>2?'&#10003;':'2' ?></div><span class="step-label">Date &amp; Time</span></div>
    <div class="step-line <?= $step>2?'done':'' ?>"></div>
    <div class="step <?= $step===3?'active':'' ?>"><div class="step-num">3</div><span class="step-label">Confirm</span></div>
  </div>
  <?php if($step===1): ?>
  <?php if(empty($doctors)): ?><div class="card" style="text-align:center;padding:44px"><p style="color:var(--text-muted)">No doctors available.</p></div>
  <?php else: ?><div class="doctors-grid">
    <?php foreach($doctors as $d): ?>
    <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="select_doctor"><input type="hidden" name="doctor_id" value="<?= (int)$d['id'] ?>">
    <button type="submit" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;padding:0">
      <div class="doctor-card" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor=''">
        <div class="doctor-card-header"><div class="doctor-info"><div class="doctor-avatar"><?= sanitize($d['initials']??'??') ?></div><div><div class="doctor-name"><?= sanitize($d['full_name']) ?></div><div class="doctor-spec"><?= sanitize($d['specialty']) ?></div><div class="doctor-avail">&#128197; <?= sanitize($d['availability']??'Mon-Fri') ?></div></div></div></div>
        <?php if(!empty($d['bio'])): ?><p class="doctor-bio"><?= sanitize($d['bio']) ?></p><?php endif; ?>
      </div>
    </button></form>
    <?php endforeach; ?></div>
  <?php endif; ?>
  <?php elseif($step===2): ?>
  <form method="POST" id="dtForm"><?= csrfField() ?>
    <input type="hidden" name="action" value="select_datetime">
    <input type="hidden" name="date" id="selDate"><input type="hidden" name="time" id="selTime">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start">
      <div>
        <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;color:var(--navy)">Select Date</h3>
        <div class="calendar"><div class="cal-header"><button type="button" onclick="changeMonth(-1)">&#8249;</button><span id="calLbl"></span><button type="button" onclick="changeMonth(1)">&#8250;</button></div><div class="cal-grid" id="calGrid"></div></div>
        <div id="dateDisplay" style="text-align:center;font-size:13px;color:var(--primary);font-weight:600;margin-top:10px;min-height:18px"></div>
      </div>
      <div>
        <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;color:var(--navy)">Select Time Slot</h3>
        <div class="time-grid"><?php foreach($timeSlots as $t): ?><div class="time-slot" data-time="<?= $t ?>" onclick="pickTime('<?= $t ?>',this)"><?= $t ?></div><?php endforeach; ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:10px"><span style="display:inline-flex;align-items:center;gap:4px;margin-right:10px"><span style="width:10px;height:10px;background:var(--primary);border-radius:2px;display:inline-block"></span>Selected</span><span style="display:inline-flex;align-items:center;gap:4px;opacity:.5"><span style="width:10px;height:10px;background:var(--border);border-radius:2px;display:inline-block"></span>Taken</span></div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:22px">
      <button type="button" class="btn btn-outline" onclick="goBack()">Back</button>
      <button type="submit" class="btn btn-primary" id="continueBtn" disabled>Continue</button>
    </div>
  </form>
  <script>
  var taken=<?= json_encode($takenSlots) ?>;var curY,curM,pDate=null,pTime=null;var now=new Date();curY=now.getFullYear();curM=now.getMonth();
  function renderCal(){var ms=['January','February','March','April','May','June','July','August','September','October','November','December'];document.getElementById('calLbl').textContent=ms[curM]+' '+curY;var g=document.getElementById('calGrid');g.innerHTML='';['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d){var h=document.createElement('div');h.className='cal-day-header';h.textContent=d;g.appendChild(h)});var fd=new Date(curY,curM,1).getDay(),ld=new Date(curY,curM+1,0).getDate();var today=new Date();today.setHours(0,0,0,0);var todayStr=today.toISOString().split('T')[0];for(var b=0;b<fd;b++){var e=document.createElement('div');e.className='cal-day other-month disabled';g.appendChild(e)}for(var d=1;d<=ld;d++){var e=document.createElement('div');e.className='cal-day';e.textContent=d;var mm=String(curM+1).padStart(2,'0'),dd=String(d).padStart(2,'0');var ds=curY+'-'+mm+'-'+dd;var thisDate=new Date(curY,curM,d);if(thisDate<today){e.classList.add('disabled')}else{if(ds===pDate)e.classList.add('selected');if(ds===todayStr)e.classList.add('today');(function(s,el){el.onclick=function(){pickDate(s,el)}})(ds,e)}g.appendChild(e);}}
  function pickDate(ds,el){pDate=ds;document.getElementById('selDate').value=ds;document.getElementById('dateDisplay').textContent='Selected: '+ds;document.querySelectorAll('.cal-day').forEach(function(d){d.classList.remove('selected')});el.classList.add('selected');refreshSlots();checkReady()}
  function refreshSlots(){var t=(pDate&&taken[pDate])?taken[pDate]:[];document.querySelectorAll('.time-slot').forEach(function(s){s.classList.remove('taken','selected');if(t.indexOf(s.dataset.time)!==-1)s.classList.add('taken')});pTime=null;document.getElementById('selTime').value=''}
  function pickTime(t,el){if(el.classList.contains('taken'))return;pTime=t;document.getElementById('selTime').value=t;document.querySelectorAll('.time-slot').forEach(function(s){s.classList.remove('selected')});el.classList.add('selected');checkReady()}
  function checkReady(){document.getElementById('continueBtn').disabled=!(pDate&&pTime)}
  function changeMonth(d){curM+=d;if(curM>11){curM=0;curY++}if(curM<0){curM=11;curY--}renderCal()}
  function goBack(){var f=document.createElement('form');f.method='POST';var a=document.createElement('input');a.type='hidden';a.name='action';a.value='back';var c=document.createElement('input');c.type='hidden';c.name='csrf_token';c.value=document.querySelector('[name=csrf_token]').value;f.appendChild(a);f.appendChild(c);document.body.appendChild(f);f.submit()}
  renderCal();
  </script>
  <?php elseif($step===3&&$selectedDoctor): ?>
  <div class="card" style="max-width:520px;margin:0 auto">
    <div class="card-header">Appointment Summary</div>
    <div class="card-body">
      <div class="summary-row"><span class="summary-label">Doctor</span><span class="summary-value"><?= sanitize($selectedDoctor['full_name']) ?></span></div>
      <div class="summary-row"><span class="summary-label">Specialty</span><span class="summary-value"><?= sanitize($selectedDoctor['specialty']) ?></span></div>
      <div class="summary-row"><span class="summary-label">Availability</span><span class="summary-value"><?= sanitize($selectedDoctor['availability']??'') ?></span></div>
      <div class="summary-row"><span class="summary-label">Date</span><span class="summary-value"><?= date('l, F j, Y',strtotime($_SESSION['book_date']??'today')) ?></span></div>
      <div class="summary-row"><span class="summary-label">Time</span><span class="summary-value"><?= sanitize($_SESSION['book_time']??'') ?></span></div>
      <div class="summary-row"><span class="summary-label">Status</span><span class="summary-value"><?= statusBadge('pending') ?></span></div> 
      <div style="display:flex;gap:12px;margin-top:6px">
        <form method="POST" style="flex:1"><?= csrfField() ?><input type="hidden" name="action" value="back"><button type="submit" class="btn btn-outline btn-block">Back</button></form>
        <form method="POST" style="flex:2"><?= csrfField() ?><input type="hidden" name="action" value="confirm"><button type="submit" class="btn btn-primary btn-block">Confirm Booking</button></form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
