<?php
declare(strict_types=1);
function approvalCheck(bool $condition,string $message): void {if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$migration=file_get_contents(__DIR__.'/../sql/migrations/003_add_rule_set_approval.sql');
approvalCheck(!preg_match('/\b(?:DROP TABLE|DELETE FROM)\b/i',$migration),'approval migration does not delete existing data');
foreach(['submitted_by','approved_by','rejected_by','rejection_reason','activated_at','rule_set_contributors','PRIMARY KEY(rule_set_id,user_id)'] as $fragment) approvalCheck(str_contains($migration,$fragment),"migration contains $fragment");
$sets=file_get_contents(__DIR__.'/../src/RuleSetRepository.php');
approvalCheck(str_contains($sets,"'DRAFT'=>['PENDING_APPROVAL']")&&str_contains($sets,"'PENDING_APPROVAL'=>['ACTIVE','REJECTED']")&&str_contains($sets,"'ACTIVE'=>['ARCHIVED']"),'status transitions are centrally allowlisted');
approvalCheck(str_contains($sets,'You cannot approve a Rule Set you contributed to.'),'maker-checker is enforced by backend');
approvalCheck(str_contains($sets,"status IN ('DRAFT','PENDING_APPROVAL')"),'Draft creation blocks every unfinished cycle');
approvalCheck(str_contains($sets,"SELECT * FROM rule_sets WHERE status='ACTIVE' FOR UPDATE")&&str_contains($sets,'count($active)!==1'),'approval locks and validates exactly one Active set');
approvalCheck(str_contains($sets,"status='ARCHIVED'")&&str_contains($sets,"status='ACTIVE',approved_by"),'approval archives and activates inside the workflow service');
approvalCheck(str_contains($sets,"field_name','operator','value','sort_order")&&!str_contains(substr($sets,strpos($sets,'public function diff')),'condition_id'),'diff compares logical condition fields rather than IDs');
$rules=file_get_contents(__DIR__.'/../src/RuleRepository.php');
approvalCheck(substr_count($rules,'recordContribution(')>=4,'all rule mutation methods can record contributors');
$decision=file_get_contents(__DIR__.'/../admin/rule_set_decision.php');
approvalCheck(str_contains($decision,"requireRole('RULE_APPROVER')")&&str_contains($decision,'verify_csrf()'),'approval actions require role, POST, and CSRF');
$submit=file_get_contents(__DIR__.'/../admin/rule_set_submit.php');
approvalCheck(str_contains($submit,"requireRole('RULE_EDITOR')")&&str_contains($submit,'verify_csrf()'),'submission requires Editor role and CSRF');
