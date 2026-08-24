<?php
declare(strict_types=1);
use DecisionRules\Database; use DecisionRules\RuleRepository;
require_once __DIR__.'/../src/bootstrap.php';
try { $repo=new RuleRepository(Database::connect(dirname(__DIR__))); $counts=$repo->counts(); $rules=$repo->recent(); $error=null; } catch(Throwable $e) { $counts=['total'=>0,'active'=>0,'hard'=>0,'review'=>0,'segmentation'=>0]; $rules=[]; $error=$e->getMessage(); }
$title='Dashboard'; $activePage='dashboard'; require __DIR__.'/partials/header.php';
?>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="cards"><article class="stat"><span class="icon blue">Σ</span><div><small>Total Rules</small><strong><?= $counts['total'] ?></strong></div></article><article class="stat"><span class="icon green">✓</span><div><small>Active Rules</small><strong><?= $counts['active'] ?></strong></div></article><article class="stat"><span class="icon red">!</span><div><small>Hard Refusal</small><strong><?= $counts['hard'] ?></strong></div></article><article class="stat"><span class="icon amber">◇</span><div><small>Risk Review</small><strong><?= $counts['review'] ?></strong></div></article><article class="stat"><span class="icon purple">⌘</span><div><small>Segmentation</small><strong><?= $counts['segmentation'] ?></strong></div></article></section>
<section class="panel"><div class="panel-head"><div><h2>Recently updated rules</h2><p>A snapshot of the current decision strategy.</p></div><a href="rules.php">View all →</a></div><?php require __DIR__.'/partials/rules_table.php'; ?></section>
<?php require __DIR__.'/partials/footer.php'; ?>

