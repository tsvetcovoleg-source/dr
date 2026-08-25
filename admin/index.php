<?php
declare(strict_types=1);
use DecisionRules\Database; use DecisionRules\RuleRepository; use DecisionRules\RuleSetRepository;
require __DIR__.'/_auth.php'; $auth->requireAnyRole(['ADMIN','RULE_EDITOR','RULE_APPROVER','VIEWER']);
try { $repo=new RuleRepository($pdo); $setRepo=new RuleSetRepository($pdo); $activeSet=$setRepo->active(); $draftSet=$setRepo->draft(); $counts=$repo->counts((int)$activeSet['id']); $rules=$repo->allActive((int)$activeSet['id']); $error=null; } catch(Throwable $e) { $counts=['total'=>0,'active'=>0,'hard'=>0,'review'=>0,'segmentation'=>0]; $rules=[]; $activeSet=['version'=>0]; $draftSet=null; $error=$e->getMessage(); }
$title='Dashboard'; $activePage='dashboard'; require __DIR__.'/partials/header.php';
?>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="cards"><article class="stat"><span class="icon blue">v</span><div><small>Active Rule Set</small><strong>v<?= (int)$activeSet['version'] ?></strong></div></article><?php if($draftSet):?><article class="stat"><span class="icon amber">v</span><div><small>Draft Rule Set</small><strong>v<?= (int)$draftSet['version'] ?></strong></div></article><?php endif;?><article class="stat"><span class="icon blue">Σ</span><div><small>Total Rules</small><strong><?= $counts['total'] ?></strong></div></article><article class="stat"><span class="icon green">✓</span><div><small>Active Rules</small><strong><?= $counts['active'] ?></strong></div></article><article class="stat"><span class="icon red">!</span><div><small>Hard Refusal</small><strong><?= $counts['hard'] ?></strong></div></article><article class="stat"><span class="icon amber">◇</span><div><small>Risk Review</small><strong><?= $counts['review'] ?></strong></div></article><article class="stat"><span class="icon purple">⌘</span><div><small>Segmentation</small><strong><?= $counts['segmentation'] ?></strong></div></article></section>
<section class="panel"><div class="panel-head"><div><h2>Active Rules</h2><p>All active rules in evaluation order.</p></div><a href="rules.php">View all →</a></div><?php require __DIR__.'/partials/rules_table.php'; ?></section>
<?php require __DIR__.'/partials/footer.php'; ?>

