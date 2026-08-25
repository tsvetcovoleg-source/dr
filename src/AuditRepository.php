<?php
declare(strict_types=1);

namespace DecisionRules;

use PDO;

/** Read-only access and presentation helpers for the existing audit_log. */
final class AuditRepository
{
    public const LABELS = [
        'RULESET_CREATED'=>'Rule Set created','RULESET_SUBMITTED'=>'Submitted for approval','RULESET_APPROVED'=>'Rule Set approved',
        'RULESET_REJECTED'=>'Rule Set rejected','RULESET_ACTIVATED'=>'Rule Set activated','RULESET_ARCHIVED'=>'Previous Rule Set archived',
        'RULE_CREATED'=>'Rule created','RULE_REPLACED'=>'Rule changed','RULE_REMOVED'=>'Rule removed',
        'LOGIN_SUCCESS'=>'Login','LOGIN_FAILED'=>'Failed login','LOGOUT'=>'Logout','USER_CREATED'=>'User created',
        'USER_UPDATED'=>'User updated','USER_ROLES_CHANGED'=>'Roles changed','USER_ACTIVATED'=>'User activated',
        'USER_DEACTIVATED'=>'User deactivated','USER_PASSWORD_RESET'=>'Password reset',
    ];
    private const SENSITIVE = ['password','password_hash','new_password','old_password','session','session_id','csrf','csrf_token','token'];

    public function __construct(private PDO $pdo) {}

    public function page(array $filters, int $page=1, int $perPage=50): array
    {
        [$where,$params]=$this->where($filters);$page=max(1,$page);$perPage=max(1,min(100,$perPage));
        $count=$this->pdo->prepare("SELECT COUNT(*) FROM audit_log a $where");$count->execute($params);$total=(int)$count->fetchColumn();
        $sql="SELECT a.* FROM audit_log a $where ORDER BY a.created_at DESC,a.id DESC LIMIT ".(int)$perPage.' OFFSET '.(($page-1)*$perPage);
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();foreach($rows as &$r)$r=$this->hydrate($r);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage)),'per_page'=>$perPage];
    }

    public function find(int $id): ?array
    { $s=$this->pdo->prepare('SELECT * FROM audit_log WHERE id=?');$s->execute([$id]);$r=$s->fetch();return $r?$this->hydrate($r):null; }

    public function ruleSetHistory(int $setId,int $limit=20): array
    { return $this->history("(a.entity_type='rule_set' AND a.entity_id=?) OR JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_set_id'))=?",[$setId,(string)$setId],$limit); }

    public function ruleHistory(int $setId,string $code,array $ids=[],int $limit=20): array
    {
        $parts=["(JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_set_id'))=? AND JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_code'))=?)"];$p=[(string)$setId,$code];
        foreach(array_unique(array_filter(array_map('intval',$ids))) as $id){$parts[]="(a.entity_type='rule' AND (a.entity_id=? OR JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.old_rule_id'))=? OR JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.new_rule_id'))=? OR JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.source_rule_id'))=?))";array_push($p,$id,(string)$id,(string)$id,(string)$id);}
        return $this->history('('.implode(' OR ',$parts).')',$p,$limit);
    }

    public function eventTypes(): array { return $this->pdo->query('SELECT DISTINCT event_type FROM audit_log ORDER BY event_type')->fetchAll(PDO::FETCH_COLUMN); }
    public static function label(string $event): string { return self::LABELS[$event]??ucwords(strtolower(str_replace('_',' ',$event))); }
    public static function category(string $event): string { return str_starts_with($event,'RULESET_')?'Rule Set':(str_starts_with($event,'RULE_')?'Rule change':(str_starts_with($event,'USER_')?'User':'Security')); }

    public static function sanitize(mixed $value,?string $key=null): mixed
    {
        if($key!==null){$normal=strtolower($key);foreach(self::SENSITIVE as $bad)if($normal===$bad||str_contains($normal,$bad))return '[redacted]';}
        if(!is_array($value))return $value;$safe=[];foreach($value as $k=>$v)$safe[$k]=self::sanitize($v,(string)$k);return $safe;
    }

    public static function diff(array $details): array
    {
        $before=is_array($details['before']??null)?$details['before']:[];$after=is_array($details['after']??null)?$details['after']:[];$out=['fields'=>[],'conditions'=>[]];
        $labels=['rule_code'=>'Rule code','stage_name'=>'Stage','avg_actual_pd'=>'Average Actual PD','priority'=>'Priority','active'=>'Status','description'=>'Description'];
        foreach($labels as $key=>$label)if(array_key_exists($key,$before)&&array_key_exists($key,$after)&&(string)$before[$key] !== (string)$after[$key])$out['fields'][]=['label'=>$label,'before'=>$before[$key],'after'=>$after[$key]];
        $bc=is_array($before['conditions']??null)?$before['conditions']:[];$ac=is_array($after['conditions']??null)?$after['conditions']:[];
        $signature=static fn(array $c):string=>implode('\x1f',[(string)($c['field_name']??''),(string)($c['operator']??''),(string)($c['value']??'')]);
        $used=[];$pending=[];foreach($bc as $b){$match=null;foreach($ac as $i=>$a)if(!isset($used[$i])&&$signature($a)===$signature($b)){$match=$i;break;}if($match!==null)$used[$match]=true;else $pending[]=$b;}
        foreach($pending as $b){$match=null;foreach($ac as $i=>$a)if(!isset($used[$i])&&($a['field_name']??null)===($b['field_name']??null)){$match=$i;break;}if($match!==null){$used[$match]=true;$out['conditions'][]=['type'=>'changed','before'=>$b,'after'=>$ac[$match]];}else $out['conditions'][]=['type'=>'removed','before'=>$b];}
        foreach($ac as $i=>$a)if(!isset($used[$i]))$out['conditions'][]=['type'=>'added','after'=>$a];return $out;
    }

    private function history(string $condition,array $params,int $limit): array
    { $s=$this->pdo->prepare("SELECT a.* FROM audit_log a WHERE $condition ORDER BY a.created_at DESC,a.id DESC LIMIT ".max(1,min(100,$limit)));$s->execute($params);$rows=$s->fetchAll();foreach($rows as &$r)$r=$this->hydrate($r);return $rows; }
    private function hydrate(array $r): array { $d=json_decode((string)($r['details']??''),true);$r['details_data']=self::sanitize(is_array($d)?$d:[]);$r['label']=self::label($r['event_type']);$r['category']=self::category($r['event_type']);return $r; }
    private function where(array $f): array
    {
        $w=[];$p=[];$add=function(string $sql,mixed $v)use(&$w,&$p){$w[]=$sql;$p[]=$v;};
        if($f['date_from']??'')$add('a.created_at >= ?', $f['date_from'].' 00:00:00');if($f['date_to']??'')$add('a.created_at < ?',date('Y-m-d 00:00:00',strtotime($f['date_to'].' +1 day')));
        if($f['user']??'')$add('a.username = ?',$f['user']);if($f['event_type']??'')$add('a.event_type = ?',$f['event_type']);
        $cats=['rule_changes'=>"a.event_type LIKE 'RULE\_%' AND a.event_type NOT LIKE 'RULESET\_%'",'rule_sets'=>"a.event_type LIKE 'RULESET\_%'",'users'=>"a.event_type LIKE 'USER\_%'",'authentication'=>"a.event_type IN ('LOGIN_SUCCESS','LOGIN_FAILED','LOGOUT')"];
        if(isset($cats[$f['category']??'']))$w[]='('.$cats[$f['category']].')';
        if($f['rule_set_version']??'')$add("JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_set_version'))=?",(string)$f['rule_set_version']);
        if($f['rule_set_id']??''){$w[]="(JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_set_id'))=? OR (a.entity_type='rule_set' AND a.entity_id=?))";array_push($p,(string)$f['rule_set_id'],(int)$f['rule_set_id']);}
        if($f['rule_code']??'')$add("JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_code')) LIKE ?",'%'.$f['rule_code'].'%');
        if($f['search']??''){$q='%'.$f['search'].'%';$w[]="(a.username LIKE ? OR a.event_type LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.rule_code')) LIKE ?)";array_push($p,$q,$q,$q);}
        return [$w?'WHERE '.implode(' AND ',$w):'',$p];
    }
}
