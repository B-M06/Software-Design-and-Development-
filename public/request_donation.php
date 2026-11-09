<?php
require_once __DIR__ . '/../app/helpers.php';
$u=require_role(['ngo','demand_reporter']);
if ($_SERVER['REQUEST_METHOD']!=='POST') redirect('food.php');
verify_csrf();
if ($u['verification_status']!=='verified') { flash('Your account must be verified before requesting donations.','error'); redirect('food.php'); }
$foodId=(int)($_POST['food_id']??0); $pdo=db();
$stmt=$pdo->prepare("SELECT sf.id,sf.restaurant_id,sf.quantity_kg,sf.safety_deadline,sf.status, EXISTS(SELECT 1 FROM food_safety_checks fsc WHERE fsc.surplus_food_id=sf.id AND fsc.result='safe') AS safety_approved FROM surplus_food sf WHERE sf.id=? FOR UPDATE");
try {
 $pdo->beginTransaction(); $stmt->execute([$foodId]); $food=$stmt->fetch();
 if (!$food || $food['status']!=='available' || !(int)$food['safety_approved'] || strtotime($food['safety_deadline'])<=time()) throw new RuntimeException('This food listing is no longer available.');
 $exists=$pdo->prepare("SELECT id FROM donation_requests WHERE surplus_food_id=? AND requested_by=? AND status IN ('requested','accepted','scheduled')"); $exists->execute([$foodId,$u['id']]);
 if ($exists->fetch()) throw new RuntimeException('You already have an active request for this listing.');
 $pdo->prepare("INSERT INTO donation_requests(surplus_food_id,requested_by,quantity_kg,status) VALUES(?,?,?,'requested')")->execute([$foodId,$u['id'],$food['quantity_kg']]);
 $restaurantNotice=$pdo->prepare("INSERT INTO notifications(user_id,title,message) SELECT restaurant_id,?,? FROM surplus_food WHERE id=?");
 $restaurantNotice->execute(['New donation request','A partner requested your surplus food listing #'.$foodId,$foodId]);
 $pdo->commit(); flash('Donation request submitted. The donor can review it now.','success');
} catch(Throwable $e) {
 if ($pdo->inTransaction()) $pdo->rollBack();
 flash($e instanceof RuntimeException?$e->getMessage():'Could not submit request. Please try again.','error');
}
redirect('requests.php');
