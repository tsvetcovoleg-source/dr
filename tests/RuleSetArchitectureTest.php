<?php
declare(strict_types=1);
function check(bool $condition,string $message): void { if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);} echo "PASS: $message\n"; }
$migration=file_get_contents(__DIR__.'/../sql/migrations/002_add_rule_sets.sql');
check(!preg_match('/\b(?:DROP TABLE|DELETE FROM)\s+(?:rules|rule_conditions)\b/i',$migration),'migration preserves existing rules and conditions');
check(str_contains($migration,"VALUES(1,'Initial Rule Set','ACTIVE')")&&str_contains($migration,'UPDATE rules SET rule_set_id=@initial_rule_set_id'),'migration assigns existing rules to v1 Active');
check(str_contains($migration,'uq_rules_set_code(rule_set_id,rule_code)'),'rule code uniqueness is scoped to a Rule Set');
check(str_contains($migration,'uq_one_active')&&str_contains($migration,'uq_one_draft'),'database permits at most one Active and one Draft');
$repository=file_get_contents(__DIR__.'/../src/RuleRepository.php');
check(str_contains($repository,"rs.status='ACTIVE' AND r.active = 1"),'evaluation query excludes Draft rules');
check(str_contains($repository,'assertDraftRule($id)'),'rule mutation paths enforce Draft status');
$sets=file_get_contents(__DIR__.'/../src/RuleSetRepository.php');
check(str_contains($sets,'beginTransaction()')&&str_contains($sets,'rollBack()'),'Draft cloning is transactional');
check(str_contains($sets,'SELECT field_name,operator,value,sort_order FROM rule_conditions'),'Draft cloning includes all conditions');
