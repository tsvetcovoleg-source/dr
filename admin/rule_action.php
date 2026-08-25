<?php
declare(strict_types=1);
use DecisionRules\Database; use DecisionRules\RuleRepository;
require __DIR__.'/_auth.php'; $auth->requireRole('RULE_EDITOR');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');} verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT); if(!$id){http_response_code(400);exit('Invalid rule');}
$repo=new RuleRepository($pdo);
if(($_POST['action']??'')==='delete'){ $repo->delete($id); $_SESSION['flash']='Rule deleted.'; }
elseif(($_POST['action']??'')==='toggle'){ $repo->setActive($id,($_POST['active']??'0')==='1'); $_SESSION['flash']='Rule status updated.'; }
else {http_response_code(400);exit('Invalid action');} redirect('rules.php');

