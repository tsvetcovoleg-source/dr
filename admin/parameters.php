<?php
declare(strict_types=1);
use DecisionRules\Database; use DecisionRules\RuleRepository;
require __DIR__.'/_auth.php'; $auth->requireAnyRole(['ADMIN','RULE_EDITOR','RULE_APPROVER','VIEWER']);
try {
    $repo = new RuleRepository(Database::connect(dirname(__DIR__)));
    $parameters = $repo->parameters();
    $totalParameters = $parameters[0]['total_parameters'] ?? 0;
    $error = null;
} catch (Throwable $e) {
    $parameters = [];
    $totalParameters = 0;
    $error = $e->getMessage();
}
$title = 'Parameters'; $activePage = 'parameters'; require __DIR__.'/partials/header.php';
?>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="cards parameter-summary"><article class="stat"><span class="icon blue">⌗</span><div><small>Total Parameters</small><strong><?= $totalParameters ?></strong></div></article></section>
<section class="panel"><div class="panel-head"><div><h2>Parameter usage</h2><p>All input parameters referenced by active and inactive rules.</p></div></div>
<div class="parameter-tools"><input type="search" placeholder="Search parameters..." aria-label="Search parameters" data-parameter-search></div>
<div class="table-wrap"><table><thead><tr><th>Parameter</th><th>Rules</th><th>Used in rules</th><th>Operators</th></tr></thead><tbody>
<?php foreach($parameters as $parameter): ?><tr data-parameter-row data-parameter-name="<?= e($parameter['field_name']) ?>"><td><code class="parameter-name"><?= e($parameter['field_name']) ?></code></td><td class="rule-count"><strong><?= $parameter['rule_count'] ?> rules</strong><small><?= $parameter['active_rule_count'] ?> active</small></td><td><div class="chip-list"><?php foreach($parameter['rules'] as $rule): ?><a class="badge stage rule-chip <?= e(strtolower(explode('_',$rule['stage_name'])[0])) ?> <?= $rule['active']?'':'inactive' ?>" href="rule_view.php?id=<?= $rule['id'] ?>" title="<?= e(str_replace('_',' ',$rule['stage_name'])) ?><?= $rule['active']?'':' · Inactive' ?>"><?= e($rule['rule_code']) ?></a><?php if(!$rule['active']): ?><span class="badge off">Inactive</span><?php endif; ?><?php endforeach; ?></div></td><td><div class="chip-list"><?php foreach($parameter['operators'] as $operator): ?><span class="badge operator-chip"><?= e($operator) ?></span><?php endforeach; ?></div></td></tr><?php endforeach; ?>
<tr class="empty" data-parameter-empty hidden><td colspan="4">No parameters match your search.</td></tr>
<?php if(!$parameters&&!$error): ?><tr class="empty"><td colspan="4">No rule parameters found.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php require __DIR__.'/partials/footer.php'; ?>
