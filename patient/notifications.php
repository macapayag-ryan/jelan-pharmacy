<?php
require_once __DIR__.'/../includes/config.php';
requireLogin(); $db=getDB(); $userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    if(($_POST['action']??'')==='mark_all_read'){$db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$userId]);redirect(SITE_URL.'/patient/notifications.php');}
    if(($_POST['action']??'')==='mark_read'){$id=(int)($_POST['id']??0);if($id)$db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id,$userId]);redirect(SITE_URL.'/patient/notifications.php');}
}
$notifs=$db->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$notifs->execute([$userId]); $notifs=$notifs->fetchAll();
$unreadCount=getUnreadCount($userId);
$iconMap=['welcome'=>['icon'=>'&#127881;','bg'=>'#e8f4f8','color'=>'var(--primary)'],'booking'=>['icon'=>'&#128197;','bg'=>'#e8f4f8','color'=>'var(--primary)'],'approve'=>['icon'=>'&#10003;','bg'=>'#f0fdf4','color'=>'var(--success)'],'reject'=>['icon'=>'&#10005;','bg'=>'#fef2f2','color'=>'var(--danger)'],'complete'=>['icon'=>'&#127919;','bg'=>'#f0fdf4','color'=>'var(--success)'],'cancel'=>['icon'=>'&#128683;','bg'=>'#fef2f2','color'=>'var(--danger)'],'record'=>['icon'=>'&#128196;','bg'=>'#e8f4f8','color'=>'var(--primary)'],'prescription'=>['icon'=>'&#128148;','bg'=>'#e8f4f8','color'=>'var(--primary)']];
$pageTitle='Notifications — Clinic'; $activePage='notifications';
include __DIR__.'/../includes/patient_header.php';
?>
<div class="patient-page">
    <div class="page-header" style="margin-bottom:20px">
        <div><div class="page-title" style="margin-bottom:0">Notifications</div><?php if($unreadCount>0): ?><p style="font-size:13px;color:var(--text-muted);margin-top:4px"><?= $unreadCount ?> unread</p><?php endif; ?></div>
        <?php if($unreadCount>0): ?>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="mark_all_read"><button type="submit" class="btn btn-outline btn-sm">Mark all as read</button></form>
        <?php endif; ?>
    </div>
    <?php if(empty($notifs)): ?>
    <div class="card" style="text-align:center;padding:48px 24px"><div style="font-size:40px;margin-bottom:14px">&#128276;</div><h3 style="margin-bottom:8px;font-family:'Playfair Display',serif">No notifications</h3><p style="color:var(--text-muted);font-size:14px">Notifications about your appointments and records will appear here.</p></div>
    <?php else: ?>
    <div class="card">
        <?php foreach($notifs as $n): $meta=$iconMap[$n['type']]??['icon'=>'&#128276;','bg'=>'#e8f4f8','color'=>'var(--primary)'];$diff=time()-strtotime($n['created_at']);$timeAgo=$diff<60?'Just now':($diff<3600?round($diff/60).'m ago':($diff<86400?round($diff/3600).'h ago':date('M j, Y',strtotime($n['created_at'])))); ?>
        <div class="notif-item <?= !$n['is_read']?'unread':'' ?>">
            <div class="notif-icon" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>"><?= $meta['icon'] ?></div>
            <div style="flex:1"><div class="notif-title"><?= sanitize($n['title']) ?></div><div class="notif-msg"><?= sanitize($n['message']) ?></div><div class="notif-time"><?= $timeAgo ?></div></div>
            <?php if(!$n['is_read']): ?>
            <form method="POST" style="flex-shrink:0"><?= csrfField() ?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button type="submit" style="background:none;border:none;cursor:pointer;color:var(--primary);font-size:12px;font-weight:600;padding:4px 8px;border-radius:4px" title="Mark as read">&#10003;</button></form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/patient_footer.php'; ?>
