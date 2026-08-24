<?php
declare(strict_types=1);
require_once __DIR__.'/../src/RuleRepository.php';
require_once __DIR__.'/../src/RuleEngine.php';
use DecisionRules\RuleEngine; use DecisionRules\RuleRepository;

final class MemoryRepository extends RuleRepository {
    public function __construct(private array $data) {}
    public function activeRules(): array { return $this->data; }
}
function rule(string $code,string $stage,int $priority,array $conditions): array { return ['id'=>$priority,'rule_code'=>$code,'stage_name'=>$stage,'avg_actual_pd'=>'1.00','priority'=>$priority,'conditions'=>array_map(fn($c)=>['field_name'=>$c[0],'operator'=>$c[1],'value'=>$c[2]],$conditions)]; }
function expect(bool $condition,string $message): void { if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);} echo "PASS: $message\n"; }
$rules=[
 rule('HR_002','HARD_REFUSAL_STAGE',20,[['BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','39'],['CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT','>','1.5'],['UNCLOSED_OTHER_CREDIT_ACCOUNTS_COUNT','>','0.5']]),
 rule('RR_TEST','RISK_REVIEW_STAGE',10,[['RISK_SCORE','>','5']]),
 rule('PS_003','PORTFOLIO_SEGMENTATION_STAGE',30,[['BANK_CREDIT_ACCOUNTS_COUNT','<=','2.5'],['HAS_ESTATE','IN','["Yes"]']]),
];
$engine=new RuleEngine(new MemoryRepository($rules));
$a=$engine->evaluate(['BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE'=>'39','CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT'=>'2','UNCLOSED_OTHER_CREDIT_ACCOUNTS_COUNT'=>'1']); expect($a['decision']==='DECLINE'&&$a['matched_rule']['rule_code']==='HR_002','Test A: HR_002 declines');
$b=$engine->evaluate(['RISK_SCORE'=>'6']); expect($b['decision']==='REVIEW','Test B: risk review result');
$c=$engine->evaluate(['BANK_CREDIT_ACCOUNTS_COUNT'=>'2','HAS_ESTATE'=>'Yes']); expect($c['decision']==='APPROVE'&&$c['matched_rule']['rule_code']==='PS_003','Test C: PS_003 approves');
$d=$engine->evaluate([]); expect(in_array('BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE',$d['missing_fields'],true)&&$d['decision']==='NO_MATCH','Test D: missing field fails and is reported');
expect(!$engine->matches('Yes','NOT_IN','["Yes"]')&&$engine->matches('No','NOT_IN','["Yes"]'),'Test E: NOT_IN');
expect($engine->matches('100','>','31.03')&&!$engine->matches('9','>','31.03'),'Test F: numeric comparison');
