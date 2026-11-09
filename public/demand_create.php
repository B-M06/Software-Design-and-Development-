<?php
require_once __DIR__ . '/../app/layout.php';
$u=require_role(['ngo','demand_reporter','admin']); $errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $area=trim($_POST['area']??''); $district=trim($_POST['district']??''); $upazila=trim($_POST['upazila']??''); $ward=trim($_POST['ward']??'');
 $people=filter_var($_POST['people_count']??null,FILTER_VALIDATE_INT); $meals=filter_var($_POST['required_meals']??null,FILTER_VALIDATE_INT);
 $needed=trim($_POST['needed_by']??''); $urgency=$_POST['urgency']??'normal'; $notes=trim($_POST['notes']??'');
 $timestamp=$needed!==''?strtotime($needed):false;
 if (!$area || !$district || !$upazila || !$people || !$meals || $people<1 || $meals<1 || !$timestamp || $timestamp<=time()) $errors[]='Enter the area, district, upazila, positive people/meals counts, and a future needed-by time.';
 if (text_length($area)>180 || text_length($district)>100 || text_length($upazila)>100 || text_length($ward)>80 || text_length($notes)>5000) $errors[]='One or more fields are too long.';
 if (!in_array($urgency,['low','normal','high','critical'],true)) $errors[]='Choose a valid urgency.';
 if (!$errors) {
  $pdo=db();
  $q=$pdo->prepare("INSERT INTO demand_requests(created_by,area,district,upazila,ward,people_count,required_meals,needed_by,urgency,status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
  $q->execute([$u['id'],$area,$district,$upazila,$ward?:null,$people,$meals,date('Y-m-d H:i:s',$timestamp),$urgency,'pending',$notes?:null]);
  $id=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)")->execute([$u['id'],'demand_submitted','demand_requests',$id,'Area: '.$area.'; district: '.$district.'; upazila: '.$upazila]);
  flash('Demand request submitted. It will appear on the public area-demand map only after admin verification.','success'); redirect('demand.php');
 }
}
page_header('Submit food demand'); ?>
<div class="page-intro"><div class="eyebrow">AREA-WISE NEEDS</div><h1>Report food <em>demand.</em></h1><p>Share the needs of a shelter, community centre or service area. Exact beneficiary names and private details are not required.</p></div>
<div class="alert warning">For privacy, map markers show an approximate area centre—not the exact home or location of people needing support. New reports stay hidden from the demand map until verified by an admin.</div>
<div class="form-panel"><?php foreach($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label class="full">Specific area / community name<input name="area" required maxlength="180" placeholder="e.g. Mirpur 10 community shelter" value="<?= e($_POST['area']??'') ?>"></label>
<label>District<input name="district" required maxlength="100" placeholder="e.g. Dhaka" value="<?= e($_POST['district']??'') ?>"></label><label>Upazila / Thana<input name="upazila" required maxlength="100" placeholder="e.g. Mirpur" value="<?= e($_POST['upazila']??'') ?>"></label>
<label>Ward (optional)<input name="ward" maxlength="80" placeholder="e.g. Ward 3" value="<?= e($_POST['ward']??'') ?>"></label>
<label>People in need<input type="number" min="1" max="1000000" name="people_count" required value="<?= e($_POST['people_count']??'') ?>"></label><label>Meals required<input type="number" min="1" max="1000000" name="required_meals" required value="<?= e($_POST['required_meals']??'') ?>"></label>
<label>Needed by<input type="datetime-local" name="needed_by" required value="<?= e($_POST['needed_by']??'') ?>"></label><label>Urgency<select name="urgency"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select></label>
<label class="full">Notes / dietary needs<textarea name="notes" rows="3" maxlength="5000" placeholder="Describe the need; do not include beneficiary names, phone numbers or other unnecessary personal information."><?= e($_POST['notes']??'') ?></textarea></label>
<div class="full"><button class="btn" type="submit">Submit demand for verification ↗</button></div></form></div>
<?php page_footer(); ?>
