<?php
require_once __DIR__ . '/../app/layout.php';
$u=require_role(['admin','restaurant','event_organizer','ngo']);$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=(string)($_POST['action']??'');
 try{
  if($action==='create'){
   if($u['role']!=='admin' && $u['verification_status']!=='verified')throw new RuntimeException('Only verified partner accounts can create API keys.');
   $label=trim((string)($_POST['label']??''));$scope=(string)($_POST['scope']??'impact:read');if($label===''||text_length($label)>120||!in_array($scope,['impact:read','donations:read'],true))throw new RuntimeException('Enter a valid key label and scope.');
   $raw='frb_'.bin2hex(random_bytes(32));$prefix=substr($raw,0,12);$hash=hash('sha256',$raw);$scopes=json_encode([$scope],JSON_THROW_ON_ERROR);
   $q=$pdo->prepare('INSERT INTO partner_api_keys(partner_id,label,key_prefix,key_hash,scopes_json,expires_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 90 DAY))');$q->execute([$u['id'],$label,$prefix,$hash,$scopes]);$_SESSION['new_partner_api_key']=$raw;flash('API key created. Copy it now; the full key will not be shown again.');
  }elseif($action==='revoke'){
   $id=(int)($_POST['key_id']??0);$q=$pdo->prepare('UPDATE partner_api_keys SET revoked_at=NOW() WHERE id=? AND partner_id=? AND revoked_at IS NULL');$q->execute([$id,$u['id']]);if(!$q->rowCount())throw new RuntimeException('Key not found or already revoked.');audit((int)$u['id'],'partner_api_key_revoked','partner_api_keys',$id);flash('API key revoked.');
  }else throw new RuntimeException('Invalid action.');
 }catch(Throwable $e){flash($e instanceof RuntimeException?$e->getMessage():'Could not save API key. Import the advanced migration and try again.','error');}
 redirect('partner_portal.php');
}
$q=$pdo->prepare('SELECT id,label,key_prefix,scopes_json,expires_at,revoked_at,created_at,last_used_at FROM partner_api_keys WHERE partner_id=? ORDER BY created_at DESC');$q->execute([$u['id']]);$keys=$q->fetchAll();$newKey=$_SESSION['new_partner_api_key']??null;unset($_SESSION['new_partner_api_key']);
page_header('Partner API portal'); ?>
<div class="page-intro"><div class="eyebrow">PLATFORM INTEGRATION</div><h1>Partner API <em>portal.</em></h1><p>Create limited-scope API keys for partner integrations. Keys expire after 90 days and can be revoked at any time.</p></div>
<?php if($newKey): ?><div class="notice-panel"><strong>Copy this API key now</strong><p><code><?= e($newKey) ?></code></p><p>This key is shown once. Store it securely and never put it in browser-side JavaScript or source control.</p><button class="btn btn-sm" type="button" data-copy-key="<?= e($newKey) ?>">Copy API key</button></div><?php endif; ?>
<section class="section"><h2>Create API key</h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Key label<input name="label" maxlength="120" placeholder="e.g. NGO reporting integration" required></label><label>Permission scope<select name="scope"><option value="impact:read">Read impact summary</option><option value="donations:read">Read completed donations</option></select></label><div class="full"><button class="btn">Create API key</button></div></form></section>
<section class="section"><h2>Your API keys</h2><div class="table-wrap"><table><thead><tr><th>Label</th><th>Prefix</th><th>Scope</th><th>Expires</th><th>Status</th><th>Last used</th><th>Action</th></tr></thead><tbody><?php foreach($keys as $k): ?><tr><td><?= e($k['label']) ?></td><td><code><?= e($k['key_prefix']) ?>…</code></td><td><?= e(implode(', ',json_decode((string)$k['scopes_json'],true)?:[])) ?></td><td><?= e($k['expires_at']??'Never') ?></td><td><?= $k['revoked_at']?'Revoked':(strtotime((string)$k['expires_at'])<time()?'Expired':'Active') ?></td><td><?= e($k['last_used_at']??'Never') ?></td><td><?php if(!$k['revoked_at']&&strtotime((string)$k['expires_at'])>time()): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>"><button class="btn btn-sm btn-danger">Revoke</button></form><?php endif; ?></td></tr><?php endforeach; if(!$keys): ?><tr><td colspan="7" class="table-empty">No API keys created.</td></tr><?php endif; ?></tbody></table></div></section>
<div class="notice-panel"><strong>Available endpoints</strong><p><code>GET <?= e(url('api/v1/impact.php')) ?></code> — requires a key with <code>impact:read</code>.</p><p><code>GET <?= e(url('api/v1/donations.php')) ?></code> — requires a key with <code>donations:read</code>.</p><p>Send <code>Authorization: Bearer YOUR_KEY</code>. Use HTTPS outside localhost. API responses are rate limited to 60 requests per key per minute.</p></div>
<script>document.querySelectorAll('[data-copy-key]').forEach(function(b){b.addEventListener('click',function(){navigator.clipboard.writeText(b.dataset.copyKey).then(function(){b.textContent='Copied';}).catch(function(){alert('Please copy the key manually.');});});});</script>
<?php page_footer(); ?>
