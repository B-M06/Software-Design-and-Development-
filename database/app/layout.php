<?php
require_once __DIR__ . '/helpers.php';
function page_header(string $title): void {
    $u = current_user();
    $flash = take_flash();
    $home = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php');
    ?><!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=footerfix4-<?= (int) @filemtime(__DIR__ . '/../public/assets/style.css') ?>">
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <meta name="theme-color" content="#176b4d">
    <script defer src="<?= e(url('assets/app.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../public/assets/app.js') ?>"></script>
    <script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('<?= e(url('sw.js')) ?>').catch(function(){});});}</script>
    </head><body class="<?= $home ? 'home-page' : 'inner-page' ?>">
    <header class="topbar">
      <a class="brand" href="<?= e(url()) ?>" aria-label="FoodRescue Bangladesh home"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M24 41C9 33 4 22 10 13c4-6 11-5 14 1 3-6 10-7 14-1 6 9 1 20-14 28Z" fill="currentColor"/><path d="M14 29c7-1 12 1 16 6M24 34c0-8 4-13 11-17" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><path d="M15 18c2-3 5-3 7 0-2 4-5 4-7 0Z" fill="#fff"/></svg></span><span class="brand-text"><strong>FoodRescue</strong><small>Bangladesh</small><span class="brand-tagline">Save Food · Feed People · Build a Better Tomorrow</span></span></a>
      <nav class="nav home-nav" aria-label="Main navigation">
        <a class="nav-home" href="<?= e(url()) ?>" data-en="Home" data-bn="হোম">Home</a>
        <a href="<?= e(url()) ?>#about" data-en="About" data-bn="আমাদের সম্পর্কে">About</a>
        <a href="<?= e(url('food.php')) ?>" data-en="Food" data-bn="খাবার">Food</a>
        <a href="<?= e(url('ngo_finder.php')) ?>" data-en="NGO" data-bn="এনজিও">NGO</a>
        <a href="<?= e(url('volunteer.php')) ?>" data-en="Volunteer" data-bn="স্বেচ্ছাসেবক">Volunteer</a>
        <a href="<?= e(url()) ?>#contact" data-en="Contact" data-bn="যোগাযোগ">Contact</a>
        <?php if ($u): ?><a class="role-nav-link" href="<?= e(url('dashboard.php')) ?>" data-en="Dashboard" data-bn="ড্যাশবোর্ড">Dashboard</a><?php else: ?><a href="<?= e(url('login.php')) ?>" data-en="Log in" data-bn="লগইন">Log in</a><a class="nav-join" href="<?= e(url('register.php')) ?>" data-en="Join us" data-bn="যুক্ত হোন">Join us</a><?php endif; ?>
      </nav>
      <div class="header-actions"><?php if ($u): $unreadCount = 0; try { $countStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $countStmt->execute([(int)$u['id']]); $unreadCount = (int)$countStmt->fetchColumn(); } catch (Throwable $e) {} ?><a class="notification-bell" id="notificationBell" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications, <?= $unreadCount ?> unread"><span aria-hidden="true">♧</span><span class="notification-badge" id="notificationCount"<?= $unreadCount ? '' : ' hidden' ?>><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span></a><?php endif; ?><div class="language-switch" role="group" aria-label="Language"><button type="button" class="lang-btn" data-lang="bn">বাংলা</button><button type="button" class="lang-btn active" data-lang="en">English</button></div><button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle theme">☼</button>
        <details class="user-menu"><summary><span class="user-avatar">♙</span><span class="hello-user"><?= $u ? e($u['name']) : 'Hello, User' ?></span><span class="user-caret">⌄</span></summary><div class="user-dropdown"><?php if ($u): ?><a href="<?= e(url('dashboard.php')) ?>">Dashboard</a><a href="<?= e(url('profile.php')) ?>">Profile</a><a href="<?= e(url('notifications.php')) ?>">Notifications</a><?php if ($u['role']==='admin'): ?><a href="<?= e(url('admin.php')) ?>">Admin panel</a><a href="<?= e(url('admin_users.php')) ?>">Manage users</a><?php endif; ?><form method="post" action="<?= e(url('logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit">Log out</button></form><?php else: ?><a href="<?= e(url('login.php')) ?>">Log in</a><a href="<?= e(url('register.php')) ?>">Create account</a><?php endif; ?><button type="button" id="banglishToggle" class="banglish-menu-toggle">বাংলা টাইপিং: চালু</button></div></details>
      </div>
    </header><main class="container">
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif;
}
function page_footer(): void {
    ?></main><footer class="footer site-footer" id="contact">
      <div class="footer-brand"><strong>FoodRescue Bangladesh</strong><p data-en="Rescue food. Reduce waste. Share hope." data-bn="খাবার বাঁচাই। অপচয় কমাই। আশার আলো ছড়াই।">Rescue food. Reduce waste. Share hope.</p><small data-en="A community-powered food rescue platform." data-bn="কমিউনিটির উদ্যোগে গড়ে ওঠা ফুড রেসকিউ প্ল্যাটফর্ম।">A community-powered food rescue platform.</small></div>
      <div class="footer-links"><strong data-en="Quick links" data-bn="দ্রুত লিংক">Quick links</strong><a href="<?= e(url()) ?>" data-en="Home" data-bn="হোম">Home</a><a href="<?= e(url()) ?>#about" data-en="About us" data-bn="আমাদের সম্পর্কে">About us</a><a href="<?= e(url('food.php')) ?>" data-en="Find food" data-bn="খাবার খুঁজুন">Find food</a><a href="<?= e(url('ngo_finder.php')) ?>" data-en="NGO partners" data-bn="এনজিও পার্টনার">NGO partners</a><a href="<?= e(url('volunteer.php')) ?>" data-en="Volunteer" data-bn="স্বেচ্ছাসেবক">Volunteer</a></div>
      <div class="footer-contact"><strong data-en="Get in touch" data-bn="যোগাযোগ করুন">Get in touch</strong><a href="mailto:info@foodrescuebd.org">✉ info@foodrescuebd.org</a><span>⌖ Dhaka, Bangladesh</span><small data-en="Currency: BDT (৳) · Asia/Dhaka" data-bn="মুদ্রা: টাকা (৳) · এশিয়া/ঢাকা">Currency: BDT (৳) · Asia/Dhaka</small></div>
      <div class="footer-social"><strong data-en="Follow the mission" data-bn="আমাদের সঙ্গে থাকুন">Follow the mission</strong><div><a href="https://www.facebook.com/" aria-label="Facebook" rel="noopener noreferrer">f</a><a href="https://www.instagram.com/" aria-label="Instagram" rel="noopener noreferrer">◎</a><a href="https://www.linkedin.com/" aria-label="LinkedIn" rel="noopener noreferrer">in</a></div><small data-en="Together, we can make a difference." data-bn="একসঙ্গে আমরা পরিবর্তন আনতে পারি।">Together, we can make a difference.</small></div>
      <div class="footer-bottom"><span>© <?= date('Y') ?> FoodRescue Bangladesh. <span data-en="All rights reserved." data-bn="সর্বস্বত্ব সংরক্ষিত।">All rights reserved.</span></span><span data-en="Rescue Food · Reduce Waste · Share Hope" data-bn="খাবার বাঁচাই · অপচয় কমাই · আশা ছড়াই">Rescue Food · Reduce Waste · Share Hope</span></div>
    </footer></body></html><?php
}
