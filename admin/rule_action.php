<?php
declare(strict_types=1);
use DecisionRules\RuleRepository;
require __DIR__.'/_auth.php';
$auth->requireRole('RULE_EDITOR');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Invalid rule');}
$repo=new RuleRepository($pdo);$user=$auth->requireLogin();$before=$repo->find($id);
if(!$before){http_response_code(404);exit('Rule not found');}
try {
    $action=(string)($_POST['action']??'');
    $version=(int)$pdo->query('SELECT version FROM rule_sets WHERE id='.(int)$before['rule_set_id'])->fetchColumn();
    $details=['rule_set_id'=>(int)$before['rule_set_id'],'rule_set_version'=>$version,'rule_code'=>$before['rule_code']];
    if($action==='delete'){
        $repo->delete($id,(int)$user['id']);
        $auth->audit()->log('RULE_REMOVED',(int)$user['id'],$user['username'],'rule',$id,$details+['old_rule_id'=>$id,'source_rule_id'=>$before['source_rule_id'],'before'=>$before]);
        $_SESSION['flash']='Rule deleted.';
    } elseif($action==='toggle'){
        $newId=$repo->setActive($id,($_POST['active']??'0')==='1',(int)$user['id']);$after=$repo->find($newId);
        $auth->audit()->log('RULE_REPLACED',(int)$user['id'],$user['username'],'rule',$newId,$details+['old_rule_id'=>$id,'new_rule_id'=>$newId,'source_rule_id'=>$after['source_rule_id'],'before'=>$before,'after'=>$after]);
        $_SESSION['flash']='Rule status updated.';
    } else {http_response_code(400);exit('Invalid action');}
} catch(DomainException $e){http_response_code(403);exit($e->getMessage());}
redirect('rules.php');
