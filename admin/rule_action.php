<?php
declare(strict_types=1);
use DecisionRules\Database; use DecisionRules\RuleRepository;
require __DIR__.'/_auth.php'; $auth->requireRole('RULE_EDITOR');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');} verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT); if(!$id){http_response_code(400);exit('Invalid rule');}
$repo=new RuleRepository($pdo); $user=$auth->requireLogin();
$rule=$repo->find($id); if(!$rule){http_response_code(404);exit('Rule not found');} try { if(($_POST['action']??'')==='delete'){ $repo->delete($id,(int)$user['id']); $_SESSION['flash']='Rule deleted.'; }
elseif(($_POST['action']??'')==='toggle'){ $repo->setActive($id,($_POST['active']??'0')==='1',(int)$user['id']); $_SESSION['flash']='Rule status updated.'; }
else {http_response_code(400);exit('Invalid action');} $event=($_POST['action']??'')==='delete'?'RULE_REMOVED':(($_POST['active']??'0')==='1'?'RULE_ACTIVATED':'RULE_DEACTIVATED');$version=(int)$pdo->query('SELECT version FROM rule_sets WHERE id='.(int)$rule['rule_set_id'])->fetchColumn();$auth->audit()->log($event,(int)$user['id'],$user['username'],'rule',$id,['rule_set_id'=>(int)$rule['rule_set_id'],'rule_set_version'=>$version,'rule_code'=>$rule['rule_code']]); } catch(DomainException $e){http_response_code(403);exit($e->getMessage());} redirect('rules.php');

