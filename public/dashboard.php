<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=require_login(); $pdo=db();
$role=$u['role'];
$counts=['food'=>0,'requests'=>0,'demand'=>0,'deliveries'=>0,'users'=>0];
try {
 if (in_array($role,['restaurant','event_organizer'],true)) {
  $q=$pdo->prepare("SELECT COUNT(*) FROM surplus_food WHERE restaurant_id=?"); $q->execute([$u['id']]); $counts['food']=(int)$q->fetchColumn();
  $q=$pdo->prepare("SELECT COUNT(*) FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE sf.restaurant_id=? AND dr.status='requested'"); $q->execute([$u['id']]); $counts['requests']=(int)$q->fetchColumn();
 } elseif (in_array($role,['ngo','demand_reporter'],true)) {
  $q=$pdo->prepare("SELECT COUNT(*) FROM demand_requests WHERE created_by=?"); $q->execute([$u['id']]); $counts['demand']=(int)$q->fetchColumn();
  $q=$pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE requested_by=?"); $q->execute([$u['id']]); $counts['requests']=(int)$q->fetchColumn();
 } elseif ($role==='volunteer') {
  $q=$pdo->prepare("SELECT COUNT(*) FROM deliveries WHERE volunteer_id=? AND status NOT IN ('completed','cancelled')"); $q->execute([$u['id']]); $counts['deliveries']=(int)$q->fetchColumn();
 } elseif ($role==='admin') {
  $counts['food']=(int)$pdo->query("SELECT COUNT(*) FROM surplus_food")->fetchColumn();
  $counts['requests']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE verification_status='pending'")->fetchColumn();
  $counts['demand']=(int)$pdo->query("SELECT COUNT(*) FROM demand_requests")->fetchColumn();
  $counts['deliveries']=(int)$pdo->query("SELECT COUNT(*) FROM deliveries")->fetchColumn();
  $counts['users']=(int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
 }
} catch(Throwable $e) {}
// Each link points to an existing module. Page-level role checks remain the source of truth.
$groups=[];
$groups['Food rescue']=[
 ['＋','Donate food','food_create.php','Post a surplus listing'],
 ['▣','My food listings','my_food.php','Manage your donations'],
 ['⌕','Find available food','food.php','Browse food listings'],
 ['↔','Donation requests','requests.php','Review and follow requests'],
 ['⌖','Smart matching','smart_matching.php','Priority-based food matches'],
 ['⌖','Area map','area_map.php','Explore food locations'],
];
$groups['Community & delivery']=[
 ['⌁','Report food demand','demand_create.php','Share local food needs'],
 ['▤','My demand reports','my_demand.php','Manage submitted needs'],
 ['♧','NGO finder','ngo_finder.php','Find nearby partner NGOs'],
 ['✓','Distribution records','distribution.php','Record food handovers'],
 ['♧','Beneficiaries','beneficiaries.php','Manage beneficiary records'],
 ['▣','NGO inventory','ngo_inventory.php','Track received food stock'],
 ['↗','Rescue tasks','volunteer.php','Find available volunteer tasks'],
 ['⇢','My deliveries','deliveries.php','Track pickup and delivery'],
 ['⌖','Route planner','route_planner.php','Plan delivery stops'],
];
$groups['Insights & coordination']=[
 ['♧','Environmental impact','environmental_impact.php','Measure food and CO₂ impact'],
 ['▥','Waste analytics','waste_analytics.php','Review category trends'],
 ['▤','Reports','reports.php','View activity reports'],
 ['⚑','Relief campaigns','campaigns.php','Coordinate food drives'],
 ['✉','Notifications','notifications.php','Important account updates'],
 ['★','Feedback','feedback.php','Review completed transactions'],
 ['↗','Donation journey','chain_of_custody.php','Follow donation handovers'],
 ['⌘','Partner API portal','partner_portal.php','Manage API integrations'],
];
$groups['Account']=[
 ['♙','My profile','profile.php','Update your account details'],
 ['▣','Impact leaderboard','leaderboard.php','See community contributions'],
 ['⚑','Complaints & support','complaints.php','Submit or review concerns'],
];
if ($role==='restaurant') { $groups['Food rescue']=array_values(array_filter($groups['Food rescue'],fn($x)=>in_array($x[2],['food_create.php','my_food.php','requests.php','area_map.php'],true))); unset($groups['Community & delivery']); }
if ($role==='event_organizer') { $groups['Food rescue']=array_values(array_filter($groups['Food rescue'],fn($x)=>in_array($x[2],['food_create.php','my_food.php','requests.php','area_map.php'],true))); unset($groups['Community & delivery']); }
if ($role==='ngo') { $groups['Food rescue']=array_values(array_filter($groups['Food rescue'],fn($x)=>in_array($x[2],['food.php','requests.php','smart_matching.php','area_map.php'],true))); }
if ($role==='demand_reporter') { $groups['Food rescue']=array_values(array_filter($groups['Food rescue'],fn($x)=>in_array($x[2],['food.php','requests.php','smart_matching.php','area_map.php'],true))); $groups['Community & delivery']=array_values(array_filter($groups['Community & delivery'],fn($x)=>in_array($x[1],['Report food demand','My demand reports','NGO finder'],true))); }
if ($role==='volunteer') { unset($groups['Food rescue']); $groups['Community & delivery']=array_values(array_filter($groups['Community & delivery'],fn($x)=>in_array($x[1],['Rescue tasks','My deliveries','Route planner','NGO finder'],true))); unset($groups['Insights & coordination']); }
if ($role==='admin') {
 $groups['Administration']=[
  ['⌘','Admin control center','admin.php','Platform overview and controls'],['♙','Manage users','admin_users.php','Review user accounts'],['✓','Review queues','admin_reviews.php','Review food safety and demand'],['⚑','Security review','admin_fraud.php','Review flagged activity'],['▤','Build reports','report_builder.php','Create custom reports'],['⌁','Demand forecast','demand_prediction.php','Explore demand estimates'],['▣','Food batch tracking','food_batch_tracking.php','Review batch traceability'],['▤','Admin logs','admin_logs.php','Audit important actions'],['▣','Admin documents','admin_document.php','Manage platform documents'],['⚑','Emergency center','emergency_center.php','Coordinate urgent food needs']
 ];
}
page_header('Dashboard');
?>
<section class="workspace-hero">
 <div><div class="eyebrow"><?= e(strtoupper(str_replace('_',' ',$role))) ?> WORKSPACE</div><h1>Welcome back, <?= e($u['name']) ?></h1><p>Your FoodRescue workspace, with the tools you need in one place.</p></div>
 <div class="workspace-hero-actions"><?= status_badge($u['verification_status']) ?><a class="btn btn-sm" href="<?= e(url('profile.php')) ?>">Edit profile ↗</a></div>
</section>
<?php if($u['verification_status']!=='verified' && $role!=='admin'): ?><div class="alert warning"><strong>Verification pending.</strong> Some actions may remain restricted until an administrator verifies your account.</div><?php endif; ?>
<section class="workspace-metrics" aria-label="Activity summary">
 <?php if(in_array($role,['restaurant','event_organizer','admin'],true)): ?><a class="metric-mini" href="<?= e(url($role==='admin'?'food.php':'my_food.php')) ?>"><span>Food listings</span><strong><?= number_format($counts['food']) ?></strong><small>View listings →</small></a><?php endif; ?>
 <?php if(in_array($role,['restaurant','event_organizer','ngo','demand_reporter','admin'],true)): ?><a class="metric-mini" href="<?= e(url('requests.php')) ?>"><span><?= $role==='admin'?'Pending verifications':'Donation requests' ?></span><strong><?= number_format($counts['requests']) ?></strong><small>Open requests →</small></a><?php endif; ?>
 <?php if(in_array($role,['ngo','demand_reporter','admin'],true)): ?><a class="metric-mini" href="<?= e(url('demand.php')) ?>"><span>Demand reports</span><strong><?= number_format($counts['demand']) ?></strong><small>View demand →</small></a><?php endif; ?>
 <?php if(in_array($role,['volunteer','admin'],true)): ?><a class="metric-mini" href="<?= e(url('deliveries.php')) ?>"><span><?= $role==='admin'?'Delivery records':'Active deliveries' ?></span><strong><?= number_format($counts['deliveries']) ?></strong><small>Track deliveries →</small></a><?php endif; ?>
 <?php if($role==='admin'): ?><a class="metric-mini" href="<?= e(url('admin_users.php')) ?>"><span>Platform users</span><strong><?= number_format($counts['users']) ?></strong><small>Manage users →</small></a><?php endif; ?>
</section>
<div class="workspace-tools"><div><div class="eyebrow">YOUR WORKSPACE</div><h2>All tools, one dashboard</h2></div><label class="workspace-search"><span>⌕</span><input type="search" id="featureSearch" placeholder="Find a feature…" aria-label="Find a dashboard feature"></label></div>
<div id="workspaceGroups" class="workspace-groups">
<?php foreach($groups as $groupName=>$items): if(!$items) continue; ?><section class="workspace-group"><div class="workspace-group-heading"><h3><?= e($groupName) ?></h3><span><?= count($items) ?> tools</span></div><div class="workspace-grid">
<?php foreach($items as $item): ?><a class="workspace-card" href="<?= e(url($item[2])) ?>" data-feature="<?= e(strtolower($item[1].' '.$item[3].' '.$groupName)) ?>"><span class="workspace-icon" aria-hidden="true"><?= e($item[0]) ?></span><span class="workspace-card-copy"><strong><?= e($item[1]) ?></strong><small><?= e($item[3]) ?></small></span><span class="workspace-arrow" aria-hidden="true">↗</span></a><?php endforeach; ?>
</div></section><?php endforeach; ?>
</div>
<script>
(function(){var input=document.getElementById('featureSearch');if(!input)return;input.addEventListener('input',function(){var q=this.value.trim().toLowerCase();document.querySelectorAll('.workspace-group').forEach(function(group){var visible=0;group.querySelectorAll('.workspace-card').forEach(function(card){var show=!q||card.getAttribute('data-feature').includes(q);card.hidden=!show;if(show)visible++;});group.hidden=visible===0;});});})();
</script>
<?php page_footer(); ?>
