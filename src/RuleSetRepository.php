<?php
declare(strict_types=1);

namespace DecisionRules;

use DomainException;
use PDO;
use Throwable;

final class RuleSetRepository
{
    public const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'ACTIVE', 'REJECTED', 'ARCHIVED'];
    public function __construct(private PDO $pdo) {}

    public function active(): ?array { return $this->byStatus('ACTIVE'); }
    public function draft(): ?array { return $this->byStatus('DRAFT'); }
    public function pending(): ?array { return $this->byStatus('PENDING_APPROVAL'); }

    public function find(int $id): ?array
    {
        $sql='SELECT rs.*, creator.username created_by_username, submitter.username submitted_by_username,
          approver.username approved_by_username, rejecter.username rejected_by_username, COUNT(r.id) rule_count
          FROM rule_sets rs LEFT JOIN users creator ON creator.id=rs.created_by
          LEFT JOIN users submitter ON submitter.id=rs.submitted_by LEFT JOIN users approver ON approver.id=rs.approved_by
          LEFT JOIN users rejecter ON rejecter.id=rs.rejected_by LEFT JOIN rules r ON r.rule_set_id=rs.id WHERE rs.id=? GROUP BY rs.id';
        $stmt=$this->pdo->prepare($sql);$stmt->execute([$id]);return $stmt->fetch()?:null;
    }
    public function all(): array
    {
        return $this->pdo->query('SELECT rs.*, creator.username created_by_username, submitter.username submitted_by_username,
          approver.username approved_by_username, rejecter.username rejected_by_username, COUNT(r.id) rule_count FROM rule_sets rs
          LEFT JOIN users creator ON creator.id=rs.created_by LEFT JOIN users submitter ON submitter.id=rs.submitted_by
          LEFT JOIN users approver ON approver.id=rs.approved_by LEFT JOIN users rejecter ON rejecter.id=rs.rejected_by
          LEFT JOIN rules r ON r.rule_set_id=rs.id GROUP BY rs.id ORDER BY rs.version DESC')->fetchAll();
    }
    public function pendingAll(): array { return array_values(array_filter($this->all(),fn($s)=>$s['status']==='PENDING_APPROVAL')); }

    public function createDraftFromActive(int $userId, ?string $name, ?string $comment): int
    {
        $this->pdo->beginTransaction();try {
            if($this->pdo->query("SELECT id FROM rule_sets WHERE status IN ('DRAFT','PENDING_APPROVAL') LIMIT 1 FOR UPDATE")->fetch()) throw new DomainException('An unfinished Rule Set change cycle already exists.');
            $active=$this->pdo->query("SELECT id FROM rule_sets WHERE status='ACTIVE' FOR UPDATE")->fetchAll();if(count($active)!==1)throw new DomainException('Exactly one Active Rule Set is required.');
            $version=(int)$this->pdo->query('SELECT COALESCE(MAX(version),0)+1 FROM rule_sets FOR UPDATE')->fetchColumn();
            $stmt=$this->pdo->prepare("INSERT INTO rule_sets(version,name,status,created_by,comment,last_modified_by,last_modified_at) VALUES(?,?,'DRAFT',?,?,?,NOW())");$stmt->execute([$version,$name?:null,$userId,$comment?:null,$userId]);$draftId=(int)$this->pdo->lastInsertId();
            $this->contribute($draftId,$userId);
            $rules=$this->pdo->prepare('SELECT * FROM rules WHERE rule_set_id=? ORDER BY id');$rules->execute([(int)$active[0]['id']]);
            $ir=$this->pdo->prepare('INSERT INTO rules(rule_set_id,source_rule_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(?,?,?,?,?,?,?,?)');$cs=$this->pdo->prepare('SELECT field_name,operator,value,sort_order FROM rule_conditions WHERE rule_id=? ORDER BY sort_order,id');$ic=$this->pdo->prepare('INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(?,?,?,?,?)');
            foreach($rules->fetchAll() as $r){$ir->execute([$draftId,$r['id'],$r['rule_code'],$r['stage_name'],$r['avg_actual_pd'],$r['priority'],$r['active'],$r['description']]);$new=(int)$this->pdo->lastInsertId();$cs->execute([(int)$r['id']]);foreach($cs->fetchAll() as $c)$ic->execute([$new,$c['field_name'],$c['operator'],$c['value'],$c['sort_order']]);}
            $this->pdo->commit();return $draftId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function recordContribution(int $setId,int $userId): void { $this->contribute($setId,$userId);$this->pdo->prepare('UPDATE rule_sets SET last_modified_by=?,last_modified_at=NOW() WHERE id=?')->execute([$userId,$setId]); }
    private function contribute(int $setId,int $userId): void {$this->pdo->prepare('INSERT IGNORE INTO rule_set_contributors(rule_set_id,user_id) VALUES(?,?)')->execute([$setId,$userId]);}

    public function submit(int $id,int $userId,string $username,?string $comment=null): void
    { $this->transaction(function()use($id,$userId,$username,$comment){$set=$this->locked($id);$this->assertTransition($set['status'],'PENDING_APPROVAL');$this->contribute($id,$userId);$this->pdo->prepare("UPDATE rule_sets SET status='PENDING_APPROVAL',submitted_by=?,submitted_at=NOW(),submission_comment=? WHERE id=?")->execute([$userId,$comment?:null,$id]);$this->audit('RULESET_SUBMITTED',$userId,$username,$set);}); }

    public function approve(int $id,int $userId,string $username): void
    { $this->transaction(function()use($id,$userId,$username){$set=$this->locked($id);$this->assertTransition($set['status'],'ACTIVE');$c=$this->pdo->prepare('SELECT 1 FROM rule_set_contributors WHERE rule_set_id=? AND user_id=?');$c->execute([$id,$userId]);if($c->fetchColumn())throw new DomainException('You cannot approve a Rule Set you contributed to.');$active=$this->pdo->query("SELECT * FROM rule_sets WHERE status='ACTIVE' FOR UPDATE")->fetchAll();if(count($active)!==1)throw new DomainException('Exactly one Active Rule Set is required.');$old=$active[0];$this->assertTransition($old['status'],'ARCHIVED');$this->pdo->prepare("UPDATE rule_sets SET status='ARCHIVED' WHERE id=?")->execute([$old['id']]);$this->audit('RULESET_ARCHIVED',$userId,$username,$old);$this->pdo->prepare("UPDATE rule_sets SET status='ACTIVE',approved_by=?,approved_at=NOW(),activated_at=NOW() WHERE id=?")->execute([$userId,$id]);$details=['previous_active_rule_set_id'=>(int)$old['id'],'previous_active_version'=>(int)$old['version']];$this->audit('RULESET_APPROVED',$userId,$username,$set,$details);$this->audit('RULESET_ACTIVATED',$userId,$username,$set,$details);}); }

    public function reject(int $id,int $userId,string $username,string $reason): void
    { $reason=trim($reason);if(mb_strlen($reason)<3)throw new DomainException('Rejection reason must be at least 3 characters.');$this->transaction(function()use($id,$userId,$username,$reason){$set=$this->locked($id);$this->assertTransition($set['status'],'REJECTED');$this->pdo->prepare("UPDATE rule_sets SET status='REJECTED',rejected_by=?,rejected_at=NOW(),rejection_reason=? WHERE id=?")->execute([$userId,$reason,$id]);$this->audit('RULESET_REJECTED',$userId,$username,$set,['rejection_reason'=>$reason]);}); }

    public function diff(int $id): array
    { $active=$this->active();if(!$active)return ['added'=>[],'removed'=>[],'modified'=>[],'unchanged'=>[]];$load=function(int $sid){$q=$this->pdo->prepare('SELECT r.*,c.field_name,c.operator,c.value,c.sort_order FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id WHERE r.rule_set_id=? ORDER BY r.rule_code,c.sort_order');$q->execute([$sid]);$out=[];foreach($q->fetchAll() as $x){$code=$x['rule_code'];if(!isset($out[$code])){$out[$code]=array_intersect_key($x,array_flip(['rule_code','stage_name','avg_actual_pd','priority','active','description']));$out[$code]['conditions']=[];}if($x['field_name']!==null)$out[$code]['conditions'][]=array_intersect_key($x,array_flip(['field_name','operator','value','sort_order']));}return $out;};$before=$load((int)$active['id']);$after=$load($id);$d=['added'=>[],'removed'=>[],'modified'=>[],'unchanged'=>[]];foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $code){if(!isset($before[$code]))$d['added'][$code]=$after[$code];elseif(!isset($after[$code]))$d['removed'][$code]=$before[$code];elseif($before[$code]!=$after[$code])$d['modified'][$code]=['before'=>$before[$code],'after'=>$after[$code]];else $d['unchanged'][$code]=$after[$code];}return $d; }

    private function locked(int $id): array {$s=$this->pdo->prepare('SELECT * FROM rule_sets WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();if(!$row)throw new DomainException('Rule Set not found.');return $row;}
    private function assertTransition(string $from,string $to): void {if(!in_array($to,['DRAFT'=>['PENDING_APPROVAL'],'PENDING_APPROVAL'=>['ACTIVE','REJECTED'],'ACTIVE'=>['ARCHIVED']][$from]??[],true))throw new DomainException("Invalid Rule Set transition: $from → $to.");}
    private function transaction(callable $fn): void {$this->pdo->beginTransaction();try{$fn();$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
    private function audit(string $event,int $uid,string $username,array $set,array $extra=[]): void {(new AuditLogger($this->pdo))->log($event,$uid,$username,'rule_set',(int)$set['id'],array_merge(['rule_set_id'=>(int)$set['id'],'rule_set_version'=>(int)$set['version']],$extra));}
    private function byStatus(string $status): ?array {$s=$this->pdo->prepare('SELECT * FROM rule_sets WHERE status=? ORDER BY version DESC LIMIT 1');$s->execute([$status]);return $s->fetch()?:null;}
}
