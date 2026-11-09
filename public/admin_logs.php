<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_role(['admin']);
$pdo = db();
$q = trim($_GET['q'] ?? '');
$entity = trim($_GET['entity'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;
$where = ['1=1'];
$args = [];
if ($q !== '') { $where[] = '(a.action LIKE ? OR a.details LIKE ? OR u.name LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($entity !== '') { $where[] = 'a.entity_type=?'; $args[] = $entity; }
$cond = implode(' AND ', $where);
$c = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a JOIN users u ON u.id=a.actor_id WHERE $cond");
$c->execute($args);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$s = $pdo->prepare("SELECT a.*, u.name AS actor_name, u.role AS actor_role FROM audit_logs a JOIN users u ON u.id=a.actor_id WHERE $cond ORDER BY a.created_at DESC, a.id DESC LIMIT $per OFFSET $off");
$s->execute($args);
$logs = $s->fetchAll();
$entities = $pdo->query('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);
$qs = http_build_query(array_filter(['q' => $q, 'entity' => $entity], fn($v) => $v !== ''));
page_header('Audit log');
?>
<div class="page-intro"><div class="eyebrow">ADMINISTRATION</div><h1>Audit <em>log.</em></h1><p>A record of important actions: verifications, suspensions, complaints, demand changes and more.</p></div>
<form class="filter-bar" method="get">
<input name="q" value="<?= e($q) ?>" placeholder="Search action, details or person">
<select name="entity"><option value="">All entities</option><?php foreach ($entities as $en): ?><option value="<?= e($en) ?>" <?= $entity === $en ? 'selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?></select>
<button class="btn" type="submit">Filter</button>
</form>
<p class="muted"><?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?> · page <?= $page ?> of <?= $pages ?></p>
<div class="table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr>
<td><?= e(date('d M Y, g:i A', strtotime($l['created_at']))) ?></td>
<td><strong><?= e($l['actor_name']) ?></strong><small class="table-sub"><?= e(ucwords(str_replace('_', ' ', $l['actor_role']))) ?></small></td>
<td><?= e($l['action']) ?></td>
<td><?= e($l['entity_type']) ?> #<?= (int)$l['entity_id'] ?></td>
<td><?= e($l['details'] ?? '') ?></td>
</tr><?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="5" class="table-empty">No log entries found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><div class="section-tight" style="display:flex;gap:10px">
<?php if ($page > 1): ?><a class="btn btn-light btn-sm" href="?<?= e($qs . ($qs ? '&' : '') . 'page=' . ($page - 1)) ?>">← Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-light btn-sm" href="?<?= e($qs . ($qs ? '&' : '') . 'page=' . ($page + 1)) ?>">Next →</a><?php endif; ?>
</div><?php endif; ?>
<?php page_footer(); ?>
