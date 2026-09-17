<?php
require_once __DIR__.'/../includes/config.php';
requireAdmin(); $db=getDB();
$filter=$_GET['action_filter']??'all';
$sql='SELECT * FROM audit_log'; $params=[];
if ($filter!=='all') { $sql.=' WHERE action LIKE ?'; $params[]='%'.$filter.'%'; }
$sql.=' ORDER BY created_at DESC LIMIT 500';
$stmt=$db->prepare($sql); $stmt->execute($params); $logs=$stmt->fetchAll();
$pageTitle='Audit Log — CyberClinic Admin'; $activePage='audit';
include __DIR__.'/../includes/admin_header.php';
?>
<div class="page-header">
  <div class="page-title" style="margin-bottom:0">Audit Log <span style="font-size:13px;font-weight:400;color:var(--text-muted)">Last 500 events</span></div>
  <select class="filter-select" onchange="location.href='audit_log.php?action_filter='+this.value">
    <?php foreach(['all'=>'All Events','login'=>'Logins','mfa'=>'MFA Events','appointment'=>'Appointments','record'=>'Records','backup'=>'Backups','doctor'=>'Doctors'] as $v=>$l): ?>
    <option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>IP Address</th><th>Detail</th></tr></thead>
  <tbody>
  <?php
  $colors=['login_success'=>'#16a34a','login_fail'=>'#ef4444','login_mfa_fail'=>'#ef4444','login_rate_limited'=>'#dc2626','mfa_enabled'=>'#3b82f6','mfa_disabled'=>'#f59e0b','logout'=>'#64748b','backup_created'=>'#1a6b8a','record_add'=>'#1a6b8a','prescription_add'=>'#1a6b8a'];
  foreach($logs as $l): $c=$colors[$l['action']]??'var(--text)'; ?>
  <tr>
    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?= sanitize($l['created_at']) ?></td>
    <td><span class="badge <?= $l['actor_type']==='admin'?'badge-approved':'badge-pending' ?>"><?= sanitize($l['actor_type']) ?></span><?php if($l['actor_id']): ?> <small style="color:var(--text-muted)">#<?= $l['actor_id'] ?></small><?php endif; ?></td>
    <td style="font-weight:600;color:<?= $c ?>;font-size:13px;font-family:monospace"><?= sanitize($l['action']) ?></td>
    <td style="color:var(--text-muted);font-size:13px"><?= $l['target_type']?sanitize($l['target_type']).' #'.$l['target_id']:'—' ?></td>
    <td style="font-family:monospace;font-size:12px;color:var(--text-muted)"><?= sanitize($l['ip_address']??'—') ?></td>
    <td style="font-size:12px;color:var(--text-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($l['detail']??'—') ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($logs)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No audit events found.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
