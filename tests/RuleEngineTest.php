<?php
declare(strict_types=1);
require_once __DIR__.'/../src/RuleRepository.php';
require_once __DIR__.'/../src/RuleEngine.php';
use DecisionRules\RuleEngine; use DecisionRules\RuleRepository;

final class MemoryRepository extends RuleRepository {
    public function __construct(private array $data) {}
    public function activeRules(): array { return $this->data; }
}
function rule(string $code,string $stage,int $priority,array $conditions,string $pd='1.00'): array { return ['id'=>$priority,'rule_code'=>$code,'stage_name'=>$stage,'avg_actual_pd'=>$pd,'priority'=>$priority,'conditions'=>array_map(fn($c)=>['field_name'=>$c[0],'operator'=>$c[1],'value'=>$c[2]],$conditions)]; }
function expect(bool $condition,string $message): void { if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);} echo "PASS: $message\n"; }
$rules=[
 rule('HR_002','HARD_REFUSAL_STAGE',20,[['BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','39'],['CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT','>','1.5'],['UNCLOSED_OTHER_CREDIT_ACCOUNTS_COUNT','>','0.5']],'51.280000000000001136'),
 rule('RR_TEST','RISK_REVIEW_STAGE',10,[['RISK_SCORE','>','5']]),
 rule('PS_003','PORTFOLIO_SEGMENTATION_STAGE',30,[['BANK_CREDIT_ACCOUNTS_COUNT','<=','2.5'],['HAS_ESTATE','IN','["Yes"]']]),
];
$engine=new RuleEngine(new MemoryRepository($rules));
$a=$engine->evaluate(['BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE'=>'39','CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT'=>'2','UNCLOSED_OTHER_CREDIT_ACCOUNTS_COUNT'=>'1']); expect($a['decision']==='DECLINE'&&$a['matched_rules'][0]['rule_code']==='HR_002','Test A: HR_002 declines');
expect(!array_key_exists('matched_rule',$a)&&array_keys($a['matched_rules'][0])===['rule_code','avg_actual_pd','priority'],'Test A response contains only the documented matched rule fields');
expect(str_contains(json_encode($a,JSON_THROW_ON_ERROR),'"avg_actual_pd":51.28'),'Test A average actual PD is rounded in JSON');
$b=$engine->evaluate(['RISK_SCORE'=>'6']); expect($b['decision']==='REVIEW','Test B: risk review result');
$c=$engine->evaluate(['BANK_CREDIT_ACCOUNTS_COUNT'=>'2','HAS_ESTATE'=>'Yes']); expect($c['decision']==='APPROVE'&&$c['matched_rules'][0]['rule_code']==='PS_003','Test C: PS_003 approves');
$d=$engine->evaluate([]); expect(in_array('BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE',$d['missing_fields'],true)&&$d['decision']==='NO_MATCH','Test D: missing field fails and is reported');
expect($d['stage']===null&&$d['matched_rules']===[]&&!array_key_exists('matched_rule',$d),'Test D: NO_MATCH has no stage or matched rules');
expect(round($a['meta']['execution_time_ms'],2)===$a['meta']['execution_time_ms'],'Execution time is rounded to two decimal places');
expect(!$engine->matches('Yes','NOT_IN','["Yes"]')&&$engine->matches('No','NOT_IN','["Yes"]'),'Test E: NOT_IN');
expect($engine->matches('100','>','31.03')&&!$engine->matches('9','>','31.03'),'Test F: numeric comparison');
