<?php
declare(strict_types=1);
// Local development helper only. Never expose this setup action on a public host.
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$localHosts = ['localhost', 'localhost:80', '127.0.0.1', '127.0.0.1:80'];
if (!in_array($host, $localHosts, true)) { http_response_code(404); exit('Not found'); }
session_start();
if (empty($_SESSION['setup_demo_token'])) $_SESSION['setup_demo_token'] = bin2hex(random_bytes(24));
$result = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['token'] ?? '');
    if (!hash_equals((string)$_SESSION['setup_demo_token'], $token)) {
        http_response_code(400); $error = 'Security token mismatch. Refresh the page and try again.';
    } elseif (($_POST['confirm'] ?? '') !== 'yes') {
        $error = 'Please confirm that this is a local development database.';
    } else {
        try {
            ob_start();
            require __DIR__ . '/../scripts/seed_demo.php';
            $result = (string)ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level()) ob_end_clean();
            $error = 'Setup failed: ' . $e->getMessage() . ' — check database/schema import and config/config.php.';
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FoodRescue Local Demo Setup</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5faf6;color:#17372a;font:16px/1.6 Arial,sans-serif}.wrap{max-width:760px;margin:7vh auto;padding:24px}.card{background:white;border:1px solid #d8e8dc;border-radius:18px;padding:28px;box-shadow:0 12px 40px #174a2b12}h1{margin-top:0;color:#176b4d}p{color:#53675b}.warning{padding:14px;border-radius:10px;background:#fff4dc;color:#704d0c}.error{padding:14px;border-radius:10px;background:#ffebe8;color:#9a2f24}.success{padding:14px;border-radius:10px;background:#e8f7ed;color:#185b36;white-space:pre-wrap;overflow-wrap:anywhere}button,a{display:inline-block;border:0;border-radius:9px;background:#08784b;color:white;padding:12px 18px;font-weight:bold;text-decoration:none;cursor:pointer}label{display:block;margin:18px 0}input[type=checkbox]{margin-right:8px}small{color:#65776b}
</style></head><body><main class="wrap"><section class="card"><h1>FoodRescue Demo Data Setup</h1><p>এই পেজটি শুধু local XAMPP development-এর জন্য। এটি demo user এবং sample food listings তৈরি/refresh করবে। গুরুত্বপূর্ণ database হলে আগে backup নাও।</p>
<div class="warning"><strong>Important:</strong> Do not run this on a live/production site. Demo account passwords will be set to <code>Demo@12345</code>, and named demo food listings may be refreshed.</div>
<?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($result !== ''): ?><h2>Seeder output</h2><pre class="success"><?= htmlspecialchars($result, ENT_QUOTES, 'UTF-8') ?></pre><p><a href="food.php">Return to Available Food</a> &nbsp; <a href="login.php">Go to Login</a></p><?php else: ?>
<form method="post"><input type="hidden" name="token" value="<?= htmlspecialchars((string)$_SESSION['setup_demo_token'], ENT_QUOTES, 'UTF-8') ?>"><label><input type="checkbox" name="confirm" value="yes" required> I confirm this is my local development database and I have backed up important data.</label><button type="submit">Create / Refresh Demo Data</button></form><?php endif; ?>
<p><small>If setup fails, import <code>database/schema.sql</code> into MySQL and check database credentials in <code>config/config.php</code>.</small></p></section></main></body></html>
