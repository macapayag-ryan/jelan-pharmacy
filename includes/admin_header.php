<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'Admin — CyberClinic') ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="layout">
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
    <div><div class="brand-name">Jelan Pharmacy &amp; Medical Laboratory </div><div class="brand-sub">"All-in-One Health Services under one roof"</div></div>
  </div>
  <nav class="sidebar-nav">
    <a href="<?= SITE_URL ?>/admin/dashboard.php"     class="<?= ($activePage??'')==='dashboard'     ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/appointments.php"  class="<?= ($activePage??'')==='appointments'  ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Appointments</a>
    <a href="<?= SITE_URL ?>/admin/doctors.php"       class="<?= ($activePage??'')==='doctors'       ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Doctors</a>
    <a href="<?= SITE_URL ?>/admin/patients.php"      class="<?= ($activePage??'')==='patients'      ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Patients</a>
    <a href="<?= SITE_URL ?>/admin/records.php"       class="<?= ($activePage??'')==='records'       ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Medical Records</a>
    <a href="<?= SITE_URL ?>/admin/prescriptions.php" class="<?= ($activePage??'')==='prescriptions' ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Prescriptions</a>
    <div class="sidebar-section-label">Analytics</div>
    <a href="<?= SITE_URL ?>/admin/reports.php"       class="<?= ($activePage??'')==='reports'       ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Reports &amp; Analytics</a>
    <a href="<?= SITE_URL ?>/admin/audit_log.php"     class="<?= ($activePage??'')==='audit'         ?'active':'' ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Audit Log</a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_name']??'A',0,1)) ?></div>
      <div><div class="user-name"><?= sanitize($_SESSION['admin_name']??'') ?></div><div class="user-email">jelanpharmacymedicallaboratory@gmail.com</div></div>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="sidebar-logout"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign out</a>
  </div>
</aside>
<main class="main-wrap"><div class="page-content">