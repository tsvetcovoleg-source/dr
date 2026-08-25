<?php
$title = $title ?? 'Dashboard';
$activePage = $activePage ?? '';
$currentUser = $auth->requireLogin($activePage==='');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> · Decision Rules</title><link rel="stylesheet" href="../assets/css/app.css"></head>
<body><button class="menu-toggle" aria-label="Toggle menu">☰</button><div class="app-shell">
<aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">DR</span><span><strong>Decision Rules</strong><small>Credit Decision Engine</small></span></a>
<nav><a class="<?= $activePage==='dashboard'?'active':'' ?>" href="index.php">▦ <span>Dashboard</span></a><a class="<?= $activePage==='rules'?'active':'' ?>" href="rules.php">≡ <span>Rules</span></a><a class="<?= $activePage==='parameters'?'active':'' ?>" href="parameters.php">⌗ <span>Parameters</span></a><?php if($auth->hasRole('RULE_EDITOR')):?><a class="<?= $activePage==='add'?'active':'' ?>" href="rule_edit.php">＋ <span>Add Rule</span></a><?php endif;?><?php if($auth->hasRole('RULE_EDITOR')||$auth->hasRole('RULE_APPROVER')):?><a class="<?= $activePage==='tester'?'active':'' ?>" href="tester.php">⌁ <span>API Tester</span></a><?php endif;?><?php if($auth->hasRole('ADMIN')):?><a class="<?= $activePage==='users'?'active':'' ?>" href="users.php">♙ <span>Users</span></a><?php endif;?></nav>
<div class="sidebar-user"><strong><?=e($currentUser['full_name'])?></strong><div class="chip-list"><?php foreach($currentUser['roles'] as $role):?><span class="badge sidebar-role"><?=e(str_replace('_',' ',$role))?></span><?php endforeach;?></div><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button>Logout</button></form></div></aside>
<main class="main"><header class="topbar"><div><p class="eyebrow">Risk Management Platform</p><h1><?= e($title) ?></h1></div><?php if($auth->hasRole('RULE_EDITOR')||$auth->hasRole('RULE_APPROVER')):?><a class="btn secondary" href="tester.php">Test API</a><?php endif;?></header><div class="content">
<?php if (!empty($_SESSION['flash'])): ?><div class="alert success"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>
