<?php
require_once __DIR__ . '/../app/helpers.php';
// Logout must be a POST with a valid CSRF token so other sites cannot log users out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(current_user() ? 'dashboard.php' : '');
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
 $p=session_get_cookie_params();
 setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
header('Location: ' . url());
exit;
