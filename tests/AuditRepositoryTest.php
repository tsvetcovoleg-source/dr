<?php
declare(strict_types=1);

require_once __DIR__.'/../src/AuditRepository.php';
use DecisionRules\AuditRepository;

function auditAssert(bool $condition,string $message):void { if(!$condition)throw new RuntimeException($message); }

$details=[
    'old_rule_id'=>10,'new_rule_id'=>11,
    'before'=>['rule_code'=>'HR_001','priority'=>10,'avg_actual_pd'=>'30.47','conditions'=>[
        ['id'=>1,'field_name'=>'AGE','operator'=>'>','value'=>'31.03'],
        ['id'=>2,'field_name'=>'UNCHANGED','operator'=>'=','value'=>'yes'],
        ['id'=>3,'field_name'=>'REMOVED','operator'=>'=','value'=>'old'],
    ]],
    'after'=>['rule_code'=>'HR_001','priority'=>20,'avg_actual_pd'=>'32.15','conditions'=>[
        ['id'=>9,'field_name'=>'AGE','operator'=>'>','value'=>'60'],
        ['id'=>10,'field_name'=>'UNCHANGED','operator'=>'=','value'=>'yes'],
        ['id'=>11,'field_name'=>'ADDED','operator'=>'=','value'=>'new'],
    ]],
];
$diff=AuditRepository::diff($details);
auditAssert(count($diff['fields'])===2,'Only changed business fields must be shown.');
auditAssert(count($diff['conditions'])===3,'Changed, removed and added conditions must be shown, but unchanged conditions hidden.');
auditAssert(array_column($diff['conditions'],'type')===['changed','removed','added'],'Condition diff types are incorrect.');

$safe=AuditRepository::sanitize(['password'=>'secret','password_hash'=>'hash','csrf_token'=>'csrf','session_id'=>'sid','nested'=>['api_token'=>'token'],'rule_code'=>'HR_001']);
auditAssert($safe['password']==='[redacted]'&&$safe['nested']['api_token']==='[redacted]','Sensitive values were not redacted.');
auditAssert($safe['rule_code']==='HR_001','Business fields must remain visible.');

$repoSource=file_get_contents(__DIR__.'/../src/AuditRepository.php');
$pageSource=file_get_contents(__DIR__.'/../admin/audit.php');
auditAssert(str_contains($repoSource,'ORDER BY a.created_at DESC,a.id DESC'),'Audit ordering must be newest first.');
auditAssert(str_contains($repoSource,'LIMIT '),'Audit queries must be bounded.');
auditAssert(!preg_match('/\b(INSERT|UPDATE|DELETE)\b/i',$repoSource),'Audit repository must remain read-only.');
auditAssert(str_contains($pageSource,"requireAnyRole(['ADMIN','RULE_EDITOR','RULE_APPROVER','VIEWER'])"),'All four roles must have access.');
auditAssert(!preg_match('/method=["\']post["\']/i',$pageSource),'Audit list must not expose mutations.');

echo "Audit repository checks passed.\n";
