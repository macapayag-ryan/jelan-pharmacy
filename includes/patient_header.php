<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'CyberClinic') ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="layout">
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
    <div><div class="brand-name">Jelan Pharmacy &amp; Medical Laboratory</div><div class="brand-sub">"All-in-One Health Services under one roof"</div></div>
  </div>
  <?php $unreadCount = isLoggedIn() ? getUnreadCount((int)$_SESSION['user_id']) : 0; ?>
  <nav class="sidebar-nav">
    <a href="<?= SITE_URL ?>/patient/dashboard.php"     class="<?= ($activePage??'')==='dashboard'     ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
    <a href="<?= SITE_URL ?>/patient/book.php"          class="<?= ($activePage??'')==='book'          ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Book Appointment</a>
    <a href="<?= SITE_URL ?>/patient/appointments.php"  class="<?= ($activePage??'')==='appointments'  ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Appointments</a>
    <a href="<?= SITE_URL ?>/patient/records.php"       class="<?= ($activePage??'')==='records'       ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>My Records</a>
    <a href="<?= SITE_URL ?>/patient/prescriptions.php" class="<?= ($activePage??'')==='prescriptions' ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Prescriptions</a>
    <a href="<?= SITE_URL ?>/patient/notifications.php" class="<?= ($activePage??'')==='notifications' ?'active':'' ?>" style="display:flex;align-items:center">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span>Notifications</span>
      <?php if($unreadCount>0): ?> <span style="background:#ef4444;color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:6px"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <div class="sidebar-section-label">Account</div>
    <a href="<?= SITE_URL ?>/patient/profile.php"       class="<?= ($activePage??'')==='profile'       ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile &amp; Security</a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name']??'P',0,1)) ?></div>
      <div><div class="user-name"><?= sanitize($_SESSION['user_name']??'') ?></div><div class="user-email">Patient Portal</div></div>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="sidebar-logout"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign out</a>
  </div>
</aside>
<main class="main-wrap"><div class="page-content">
