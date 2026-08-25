<?php
declare(strict_types=1);

namespace DecisionRules;

use PDO;

/** Storage and read-only querying for API evaluations; deliberately separate from audit_log. */
final class EvaluationLogRepository
{
    private const SENSITIVE = ['password','password_hash','token','authorization','api_key','secret','csrf','session'];

    public function __construct(private PDO $pdo) {}

    public static function requestId(): string
    {
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&0x0f)|0x40);$b[8]=chr((ord($b[8])&0x3f)|0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));
    }

    public static function sanitize(mixed $value,?string $key=null): mixed
    {
        if($key!==null){$key=strtolower($key);foreach(self::SENSITIVE as $bad)if($key===$bad||str_contains($key,$bad))return '[REDACTED]';}
        if(!is_array($value))return $value;$out=[];foreach($value as $k=>$v)$out[$k]=self::sanitize($v,(string)$k);return $out;
    }

    public function start(array $data): int
    {
        $s=$this->pdo->prepare("INSERT INTO evaluation_logs(request_id,received_at,http_method,endpoint,client_ip,user_agent,request_payload,log_status) VALUES(?,?,?,?,?,?,?,'PROCESSING')");
        $s->execute([$data['request_id'],$data['received_at'],$data['http_method'],$data['endpoint'],$data['client_ip']??null,$data['user_agent']??null,$this->json(self::sanitize($data['request_payload']??[]))]);
        return (int)$this->pdo->lastInsertId();
    }

    public function complete(int $id,array $data): void { $this->finish($id,$data,'COMPLETED'); }
    public function fail(int $id,array $data): void { $this->finish($id,$data,'ERROR'); }

    public function find(int $id): ?array
    { $s=$this->pdo->prepare('SELECT * FROM evaluation_logs WHERE id=?');$s->execute([$id]);$r=$s->fetch();return $r?$this->hydrate($r):null; }

    public function page(array $f,int $page=1,int $perPage=50): array
    {
        [$where,$p]=$this->where($f);$page=max(1,$page);$perPage=max(1,min(100,$perPage));$c=$this->pdo->prepare("SELECT COUNT(*) FROM evaluation_logs e $where");$c->execute($p);$total=(int)$c->fetchColumn();
        $s=$this->pdo->prepare("SELECT * FROM evaluation_logs e $where ORDER BY received_at DESC,id DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));$s->execute($p);$rows=$s->fetchAll();foreach($rows as &$r)$r=$this->hydrate($r);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
    }

    private function finish(int $id,array $d,string $status): void
    {
        $response=self::sanitize($d['response_payload']??[]);$ruleSet=is_array($response['rule_set']??null)?$response['rule_set']:[];$matched=is_array($response['matched_rules']??null)?$response['matched_rules']:[];
        $error=is_array($response['error']??null)?$response['error']:[];
        $s=$this->pdo->prepare('UPDATE evaluation_logs SET completed_at=?,response_payload=?,http_status=?,success=?,decision=?,stage=?,rule_set_id=?,rule_set_version=?,matched_rules=?,error_code=?,execution_time_ms=?,log_status=? WHERE id=?');
        $s->execute([$d['completed_at'],$this->json($response),$d['http_status'],isset($response['success'])?(int)(bool)$response['success']:null,$response['decision']??null,$response['stage']??null,$ruleSet['id']??null,$ruleSet['version']??null,$this->json($matched),$error['code']??null,$d['execution_time_ms'],$status,$id]);
    }

    private function where(array $f): array
    {
        $w=[];$p=[];$add=function(string $sql,mixed $v)use(&$w,&$p){$w[]=$sql;$p[]=$v;};
        if($f['date_from']??'')$add('e.received_at>=?',$f['date_from'].' 00:00:00');if($f['date_to']??'')$add('e.received_at<?',date('Y-m-d 00:00:00',strtotime($f['date_to'].' +1 day')));
        foreach(['request_id','decision','stage','rule_set_version','http_status'] as $k)if(($f[$k]??'')!=='')$add("e.$k=?",$f[$k]);
        if(($f['result']??'')==='success')$add('e.success=?',1);elseif(($f['result']??'')==='error')$add('e.success=?',0);
        if($f['rule_code']??'')$add("JSON_SEARCH(e.matched_rules,'one',?,NULL,'$[*].rule_code') IS NOT NULL",$f['rule_code']);
        return [$w?'WHERE '.implode(' AND ',$w):'',$p];
    }
    private function hydrate(array $r): array { foreach(['request_payload','response_payload','matched_rules'] as $k){$v=json_decode((string)($r[$k]??''),true);$r[$k.'_data']=is_array($v)?$v:[];}return $r; }
    private function json(array $v): string { return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
}
