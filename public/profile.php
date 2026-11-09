<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_login();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';
    if ($form === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if (text_length($name) < 2) $errors[] = 'Please enter your full name or organization contact name.';
        if (!bangladesh_phone_valid($phone)) $errors[] = 'Enter a valid Bangladesh mobile number (01XXXXXXXXX or +8801XXXXXXXXX).';
        if ($address === '') $errors[] = 'Enter your area and address in Bangladesh.';
        if (!$errors) {
            $pdo->prepare('UPDATE users SET name=?, phone=?, address=? WHERE id=?')->execute([$name, $phone, $address, $u['id']]);
            flash('Profile updated.', 'success');
            redirect('profile.php');
        }
    } elseif ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $q = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
        $q->execute([$u['id']]);
        $hash = (string)$q->fetchColumn();
        if (!password_verify($current, $hash)) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'New password and confirmation do not match.';
        if (!$errors) {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('Password changed.', 'success');
            redirect('profile.php');
        }
    }
}

$q = $pdo->prepare('SELECT name,email,phone,address,role,verification_status,created_at FROM users WHERE id=?');
$q->execute([$u['id']]);
$me = $q->fetch();
page_header('My profile');
?>
<div class="page-intro"><div class="eyebrow">ACCOUNT</div><h1>My <em>profile.</em></h1><p>Update your contact details or change your password.</p></div>
<?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
<div class="form-panel">
<p><?= e(ucwords(str_replace('_', ' ', $me['role']))) ?> · <?= status_badge($me['verification_status']) ?> · <small class="muted"><?= e($me['email']) ?> (email cannot be changed)</small></p>
<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="form" value="profile">
<label class="full">Full name / organization contact<input name="name" required value="<?= e($_POST['name'] ?? $me['name']) ?>"></label>
<label>Bangladesh mobile<input name="phone" required value="<?= e($_POST['phone'] ?? $me['phone']) ?>"></label>
<label class="full">Address<input name="address" required value="<?= e($_POST['address'] ?? $me['address']) ?>"></label>
<div class="full"><button class="btn" type="submit">Save profile</button></div>
</form>
</div>
<div class="form-panel" style="margin-top:20px">
<h2>Change password</h2>
<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="form" value="password">
<label class="full">Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
<label>New password (min 8 characters)<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
<label>Confirm new password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
<div class="full"><button class="btn" type="submit">Change password</button></div>
</form>
</div>
<?php page_footer(); ?>
