<?php
require_once __DIR__ . '/../app/layout.php';
$u=require_role(['ngo','restaurant','event_organizer','admin']);
$id=(int)($_GET['id']??0); $pdo=db();
$sql="SELECT dr.id,dr.quantity_kg,dr.status,dr.updated_at,sf.food_name,rest.name AS donor,ngo.name AS ngo_name FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rest ON rest.id=sf.restaurant_id JOIN users ngo ON ngo.id=dr.requested_by WHERE dr.id=? AND dr.status IN ('delivered','completed')";
$params=[$id];
if($u['role']==='ngo') $sql.=' AND dr.requested_by=?';
elseif(in_array($u['role'],['restaurant','event_organizer'],true)) $sql.=' AND sf.restaurant_id=?';
if($u['role']!=='admin') $params[]=$u['id'];
$q=$pdo->prepare($sql);$q->execute($params);$cert=$q->fetch();
page_header('Food rescue certificate');
?>
<div class="page-intro no-print"><div class="eyebrow">IMPACT DOCUMENTS</div><h1>Donation <em>certificate.</em></h1><p>Certificates are available for delivered or completed donations associated with your account.</p></div>
<?php if(!$cert): ?>
<div class="empty-state no-print"><div>📄</div><h3>Certificate not available</h3><p>Use a completed donation ID that belongs to your account. Admins can view any completed donation.</p><a class="btn" href="<?= e(url('requests.php')) ?>">View donation requests</a></div>
<?php else: ?>
<section class="certificate-paper"><div class="certificate-logo">♥ <span>FoodRescue <small>Bangladesh</small></span></div><div class="certificate-kicker">CERTIFICATE OF FOOD RESCUE</div><h1>Certificate of Appreciation</h1><p class="certificate-intro">This certificate recognizes the contribution of</p><h2><?= e($cert['donor']) ?></h2><p>for donating safe surplus food through FoodRescue Bangladesh to support local communities.</p><div class="certificate-quantity"><?= e((string)$cert['quantity_kg']) ?> kg <small>FOOD RESCUED</small></div><p class="certificate-details">Food item: <strong><?= e($cert['food_name']) ?></strong><br>Receiving organization: <strong><?= e($cert['ngo_name']) ?></strong><br>Donation reference: <strong>FR-<?= str_pad((string)$cert['id'],6,'0',STR_PAD_LEFT) ?></strong><br>Completion date: <strong><?= e(date('d F Y',strtotime($cert['updated_at']))) ?></strong></p><div class="certificate-signatures"><div><strong>FoodRescue Bangladesh</strong><small>Digital acknowledgement</small></div><div><strong><?= e($cert['ngo_name']) ?></strong><small>Receiving organization</small></div></div></section>
<div class="certificate-actions no-print"><button class="btn" onclick="window.print()" type="button">Print / Save as PDF ↗</button><a class="btn btn-light" href="<?= e(url('reports.php')) ?>">Back to impact reports</a></div>
<?php endif; ?>
<?php page_footer(); ?>
