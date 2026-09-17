<?php
require_once __DIR__ . '/includes/config.php';
if (!empty($_SESSION['user_id']))  auditLog('patient',(int)$_SESSION['user_id'],'logout');
if (!empty($_SESSION['admin_id'])) auditLog('admin',(int)$_SESSION['admin_id'],'logout');
$_SESSION=[];
if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
session_destroy();
header('Location: '.SITE_URL.'/login.php');
exit;
