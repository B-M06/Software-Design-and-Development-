<?php
require_once __DIR__ . '/../database/app/layout.php';
if (current_user()) redirect('dashboard.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 verify_csrf();
 $name=trim($_POST['name'] ?? ''); $email=strtolower(trim($_POST['email'] ?? ''));
 $phone=trim($_POST['phone'] ?? ''); $role=$_POST['role'] ?? '';
 $password=$_POST['password'] ?? ''; $address=trim($_POST['address'] ?? '');
 $allowed=['restaurant','event_organizer','ngo','demand_reporter','volunteer'];
 if (text_length($name)<2) $errors[]='Please enter your full name or organization contact name.';
 if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Enter a valid email address.';
 if (!bangladesh_phone_valid($phone)) $errors[]='Enter a valid Bangladesh mobile number (01XXXXXXXXX or +8801XXXXXXXXX).';
 if (!in_array($role,$allowed,true)) $errors[]='Select a valid account type.';
 if (strlen($password)<8) $errors[]='Password must be at least 8 characters.';
 if (!$address) $errors[]='Enter your area and address in Bangladesh.';
 $docName=null;
 if (!$errors) { try { $docName=safe_document_upload('verification_doc','doc'); if ($docName===null) $errors[]='Upload a verification document (trade licence, NGO registration or national ID).'; } catch (RuntimeException $ex) { $errors[]=$ex->getMessage(); } }
 if (!$errors) {
  try {
   $stmt=db()->prepare("INSERT INTO users(name,email,phone,password_hash,role,address,verification_document,verification_status) VALUES(?,?,?,?,?,?,?,?)");
   $stmt->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT),$role,$address,$docName,'pending']);
   flash('Registration submitted. Sign in after your account is created; organization verification may be required.','success');
   redirect('login.php');
  } catch (PDOException $e) { if ($docName) @unlink(DOC_DIR.'/'.$docName); $errors[]=$e->getCode()==='23000'?'This email is already registered.':'Could not create account. Check database setup and try again.'; }
 }
}
page_header('Create account');
?>
<div class="auth-layout"><div class="auth-aside"><div class="eyebrow">JOIN THE NETWORK</div><h1>Make surplus<br><em>matter.</em></h1><p>Create an account to donate surplus food from restaurants or events, respond to local demand, or help with deliveries.</p><div class="aside-point">✓ Bangladesh-first design</div><div class="aside-point">✓ Safety & accountability</div><div class="aside-point">✓ Community-led impact</div></div>
<div class="auth-card"><div class="eyebrow">GET STARTED</div><h2>Create your account</h2><p class="muted">All fields are required unless marked optional.</p>
<?php foreach($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="form-grid" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label class="full">Full name / organization contact<input name="name" required value="<?= e($_POST['name']??'') ?>" placeholder="e.g. Bristy / Green Restaurant"></label>
<label>Email address<input type="email" name="email" required value="<?= e($_POST['email']??'') ?>" placeholder="you@example.com"></label>
<label>Bangladesh mobile<input name="phone" required value="<?= e($_POST['phone']??'') ?>" placeholder="01XXXXXXXXX"></label>
<label class="full">Account type<select name="role" required><option value="">Choose your role</option><option value="restaurant">Restaurant / Food donor</option><option value="event_organizer">Event organizer (wedding, conference, party, community event)</option><option value="ngo">NGO / Receiving organization</option><option value="demand_reporter">Demand reporter (shelter / orphanage / field worker)</option><option value="volunteer">Volunteer / Delivery partner</option></select></label>
<label class="full">Area and address<input name="address" required value="<?= e($_POST['address']??'') ?>" placeholder="Area, Thana, District"></label>
<label class="full">Verification document (trade licence, NGO registration or national ID; JPG, PNG, WEBP or PDF, max 3 MB)<input type="file" name="verification_doc" required accept="image/jpeg,image/png,image/webp,application/pdf"></label>
<label class="full">Password<input type="password" name="password" minlength="8" required placeholder="At least 8 characters"></label>
<div class="full"><button class="btn btn-block" type="submit">Create account ↗</button></div></form><p class="auth-switch">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p></div></div>
<?php page_footer(); ?>
