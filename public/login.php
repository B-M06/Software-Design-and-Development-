<?php
require_once __DIR__ . '/../database/app/layout.php';
if (current_user()) redirect('dashboard.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??'');
 if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
  $error='Enter a valid email address and password.';
 } else {
  try {
   if (login_throttled($email)) {
    $error='Too many failed attempts. Please wait 15 minutes and try again.';
   } else {
    $stmt=db()->prepare("SELECT id,name,email,phone,password_hash,role,verification_status,is_active FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]); $u=$stmt->fetch();
    if ($u && (int)$u['is_active']===1 && password_verify($password,$u['password_hash'])) {
     session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user']=$u;
     clear_login_failures($email);
     db()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]);
     redirect('dashboard.php');
    } else { record_login_failure($email); $error='Email or password is incorrect, or this account is inactive.'; }
   }
  } catch (Throwable $ex) {
   error_log('FoodRescue login error: ' . $ex->getMessage());
   $error='Login is temporarily unavailable. Please check the database setup and try again.';
  }
 }
}
page_header('Log in');
?>
<div class="auth-layout login-layout"><div class="auth-aside"><div class="eyebrow">WELCOME BACK</div><h1>Good to see<br><em>you again.</em></h1><p>Every rescued meal starts with people who care.</p><div class="quote-mark">“</div><blockquote>Food is too valuable to waste, and community is too powerful to ignore.</blockquote></div><div class="auth-card"><div class="eyebrow">YOUR ACCOUNT</div><h2>Log in</h2><p class="muted">Access your FoodRescue workspace.</p>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="full">Email address<input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email']??'') ?>"></label><label class="full">Password<input type="password" name="password" required autocomplete="current-password"></label><div class="full"><button class="btn btn-block" type="submit">Log in securely ↗</button></div></form><p class="auth-switch">New to FoodRescue? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
<?php $localHost = in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['localhost','localhost:80','127.0.0.1','127.0.0.1:80'], true); if ($localHost): ?>
<details class="demo-credentials"><summary>Local demo accounts (development only)</summary><p>Shared demo password: <code>Demo@12345</code></p><div class="demo-account-list">
<?php foreach ([['Admin','admin@foodrescue.local'],['Restaurant','restaurant@foodrescue.local'],['Event Organizer','event@foodrescue.local'],['NGO','ngo@foodrescue.local'],['Demand Reporter','reporter@foodrescue.local'],['Volunteer','volunteer@foodrescue.local']] as [$roleName,$demoEmail]): ?><div><span><?= e($roleName) ?></span><code><?= e($demoEmail) ?></code><button type="button" class="btn btn-sm demo-fill" data-email="<?= e($demoEmail) ?>">Use</button></div><?php endforeach; ?>
</div><small>These accounts exist only after setup. If login fails, <a href="<?= e(url('setup_demo.php')) ?>">click here to set up demo accounts and food data</a>. Local development only.</small></details>
<script>document.querySelectorAll('.demo-fill').forEach(b=>b.addEventListener('click',()=>{const f=document.querySelector('input[name=email]');if(f)f.value=b.dataset.email;const pw=document.querySelector('input[name=password]');if(pw)pw.value='Demo@12345';}));</script>
<?php endif; ?></div></div>
<?php page_footer(); ?>
