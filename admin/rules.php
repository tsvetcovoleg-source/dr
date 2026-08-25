<?php
declare(strict_types=1);
use DecisionRules\Database; use DecisionRules\RuleRepository;
require __DIR__.'/_auth.php'; $auth->requireAnyRole(['ADMIN','RULE_EDITOR','RULE_APPROVER','VIEWER']);
try { $repo=new RuleRepository(Database::connect(dirname(__DIR__))); $rules=$repo->all(); $error=null; } catch(Throwable $e) { $rules=[]; $error=$e->getMessage(); }
$title='Rules'; $activePage='rules'; require __DIR__.'/partials/header.php';
?>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><section class="panel"><div class="panel-head"><div><h2>Decision rule inventory</h2><p>Rules are evaluated by stage, priority, then identifier.</p></div><?php if($auth->hasRole('RULE_EDITOR')):?><a class="btn primary" href="rule_edit.php">+ Add Rule</a><?php endif;?></div><?php $showActions=$auth->hasRole('RULE_EDITOR'); require __DIR__.'/partials/rules_table.php'; ?></section><?php require __DIR__.'/partials/footer.php'; ?>

