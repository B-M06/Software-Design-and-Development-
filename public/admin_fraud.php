<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=require_role(['admin']); $pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$id=(int)($_POST['flag_id']??0);$status=$_POST['status']??'';$note=trim($_POST['note']??'');
 if(!in_array($status,['reviewing','cleared','action_taken'],true)){flash('Invalid review status.','error');redirect('admin_fraud.php');}
 $q=$pdo->prepare("UPDATE fraud_flags SET status=?,reviewed_by=?,reviewed_at=NOW(),details=CONCAT(details, ?) WHERE id=? AND status IN ('open','reviewing')");
 $suffix=$note!==''?"\nAdmin note: ".$note:'';$q->execute([$status,$u['id'],$suffix,$id]);
 if($q->rowCount()) {audit((int)$u['id'],'fraud_flag_'.$status,'fraud_flags',$id,$note?:null);flash('Fraud flag updated.','success');} else flash('Flag not found or already closed.','error');
 redirect('admin_fraud.php');
}
$rows=$pdo->query("SELECT f.*,u.name AS user_name,sf.food_name FROM fraud_flags f LEFT JOIN users u ON u.id=f.user_id LEFT JOIN surplus_food sf ON sf.id=f.food_id ORDER BY FIELD(f.status,'open','reviewing','action_taken','cleared'),FIELD(f.severity,'high','medium','low'),f.created_at DESC LIMIT 300")->fetchAll();
page_header('Fraud and security review'); ?>
<div class="page-intro"><div class="eyebrow">ADMIN · TRUST & SAFETY</div><h1>Fraud & security <em>review.</em></h1><p>Review manually or automatically raised risk flags. A flag is an allegation or signal, not proof of wrongdoing.</p></div>
<div class="alert warning">Review evidence before taking action. Do not automatically suspend users based only on an algorithmic risk score.</div>
<div class="table-wrap"><table><thead><tr><th>Flag</th><th>User / food</th><th>Details</th><th>Status</th><th>Review</th></tr></thead><tbody>
<?php foreach($rows as $f): ?><tr><td><strong><?= e($f['flag_type']) ?></strong><small class="table-sub">Severity: <?= e($f['severity']) ?> · #<?= (int)$f['id'] ?></small><small class="table-sub"><?= e(date('d M Y H:i',strtotime($f['created_at']))) ?></small></td><td><?= e($f['user_name']??'Unknown / removed user') ?><small class="table-sub"><?= e($f['food_name']??'No food linked') ?></small></td><td><?= nl2br(e($f['details'])) ?></td><td><?= status_badge($f['status']) ?></td><td><?php if(in_array($f['status'],['open','reviewing'],true)): ?><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="flag_id" value="<?= (int)$f['id'] ?>"><select name="status"><option value="reviewing">Mark reviewing</option><option value="cleared">Clear flag</option><option value="action_taken">Action taken</option></select><input name="note" placeholder="Review note (optional)"><button class="btn btn-sm">Save review</button></form><?php else: ?><span class="muted">Reviewed <?= $f['reviewed_at']?e(date('d M Y',strtotime($f['reviewed_at']))):'' ?></span><?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="5">No risk flags recorded. Continue reviewing audit logs and user reports.</td></tr><?php endif; ?>
</tbody></table></div><?php page_footer(); ?>
