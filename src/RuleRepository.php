<?php
declare(strict_types=1);

namespace DecisionRules;

use PDO;
use Throwable;

class RuleRepository
{
    public const STAGES = ['HARD_REFUSAL_STAGE', 'RISK_REVIEW_STAGE', 'PORTFOLIO_SEGMENTATION_STAGE'];
    public const OPERATORS = ['>', '>=', '<', '<=', '=', '!=', 'IN', 'NOT_IN', 'IS_NULL', 'IS_NOT_NULL'];

    public function __construct(private PDO $pdo) {}

    public function activeRules(): array
    {
        $sql = "SELECT r.*, c.id condition_id, c.field_name, c.operator, c.value condition_value, c.sort_order
                FROM rules r INNER JOIN rule_sets rs ON rs.id=r.rule_set_id LEFT JOIN rule_conditions c ON c.rule_id = r.id
                WHERE rs.status='ACTIVE' AND r.active = 1
                ORDER BY FIELD(r.stage_name, 'HARD_REFUSAL_STAGE','RISK_REVIEW_STAGE','PORTFOLIO_SEGMENTATION_STAGE'), r.priority, r.id, c.sort_order, c.id";
        return $this->group($this->pdo->query($sql)->fetchAll());
    }

    public function activeRuleSet(): ?array
    {
        $row=$this->pdo->query("SELECT id,version FROM rule_sets WHERE status='ACTIVE' LIMIT 1")->fetch();
        return $row ?: null;
    }

    public function all(?int $ruleSetId = null): array
    {
        $sql = 'SELECT r.*, COUNT(c.id) condition_count FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id'.($ruleSetId?' WHERE r.rule_set_id=?':'').' GROUP BY r.id ORDER BY FIELD(r.stage_name, ?, ?, ?), r.priority, r.id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ruleSetId ? array_merge([$ruleSetId],self::STAGES) : self::STAGES);
        return $stmt->fetchAll();
    }

    public function allActive(?int $ruleSetId = null): array
    {
        $sql = 'SELECT r.*, COUNT(c.id) condition_count FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id WHERE r.active=1'.($ruleSetId?' AND r.rule_set_id=?':'').' GROUP BY r.id ORDER BY FIELD(r.stage_name, ?, ?, ?), r.priority, r.id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ruleSetId ? array_merge([$ruleSetId], self::STAGES) : self::STAGES);
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, COUNT(c.id) condition_count FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id GROUP BY r.id ORDER BY r.updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function counts(?int $ruleSetId = null): array
    {
        $where = $ruleSetId ? ' WHERE rule_set_id='.(int)$ruleSetId : '';
        $row = $this->pdo->query("SELECT COUNT(*) total, SUM(active=1) active, SUM(stage_name='HARD_REFUSAL_STAGE') hard, SUM(stage_name='RISK_REVIEW_STAGE') review, SUM(stage_name='PORTFOLIO_SEGMENTATION_STAGE') segmentation FROM rules".$where)->fetch();
        return array_map('intval', $row ?: []);
    }

    public function parameters(?int $ruleSetId = null): array
    {
        $sql = "SELECT c.field_name, c.operator, r.id rule_id, r.rule_code, r.stage_name, r.active, r.priority,
                       (SELECT COUNT(DISTINCT cc.field_name) FROM rule_conditions cc INNER JOIN rules rr ON rr.id=cc.rule_id WHERE rr.rule_set_id=r.rule_set_id) total_parameters
                FROM rule_conditions c
                INNER JOIN rules r ON r.id = c.rule_id
                ".($ruleSetId ? 'WHERE r.rule_set_id='.(int)$ruleSetId : '')."
                ORDER BY c.field_name ASC,
                         FIELD(r.stage_name, 'HARD_REFUSAL_STAGE','RISK_REVIEW_STAGE','PORTFOLIO_SEGMENTATION_STAGE'),
                         r.priority, r.id, c.id";
        $parameters = [];
        foreach ($this->pdo->query($sql)->fetchAll() as $row) {
            $field = $row['field_name'];
            if (!isset($parameters[$field])) {
                $parameters[$field] = [
                    'field_name' => $field,
                    'operators' => [],
                    'rules' => [],
                    'total_parameters' => (int) $row['total_parameters'],
                ];
            }
            $parameters[$field]['operators'][$row['operator']] = $row['operator'];
            $ruleId = (int) $row['rule_id'];
            $parameters[$field]['rules'][$ruleId] = [
                'id' => $ruleId,
                'rule_code' => $row['rule_code'],
                'stage_name' => $row['stage_name'],
                'active' => (bool) $row['active'],
                'priority' => (int) $row['priority'],
            ];
        }
        return array_values(array_map(static function (array $parameter): array {
            $parameter['operators'] = array_values($parameter['operators']);
            $parameter['rules'] = array_values($parameter['rules']);
            $parameter['rule_count'] = count($parameter['rules']);
            $parameter['active_rule_count'] = count(array_filter($parameter['rules'], static fn (array $rule): bool => $rule['active']));
            return $parameter;
        }, $parameters));
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, c.id condition_id, c.field_name, c.operator, c.value condition_value, c.sort_order FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id WHERE r.id=? ORDER BY c.sort_order,c.id');
        $stmt->execute([$id]);
        $rules = $this->group($stmt->fetchAll());
        return $rules[0] ?? null;
    }

    public function save(array $rule, array $conditions, ?int $id = null, ?int $userId = null): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($id) {
                $snapshot=$this->draftSnapshot($id);
                $rule['rule_set_id']=(int)$snapshot['rule_set_id'];
                $rule['source_rule_id']=$snapshot['source_rule_id']===null?null:(int)$snapshot['source_rule_id'];
                $this->pdo->prepare('DELETE FROM rules WHERE id=?')->execute([$id]);
            } else {
                $this->assertDraftSet((int)$rule['rule_set_id']);
                $rule['source_rule_id']=null;
            }
            $id=$this->insertRule($rule,$conditions);
            if ($userId !== null) $this->recordContribution((int)$rule['rule_set_id'], $userId);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    public function setActive(int $id, bool $active, ?int $userId = null): int
    {
        $this->pdo->beginTransaction();
        try {
            $snapshot=$this->draftSnapshot($id);$conditions=$snapshot['conditions'];unset($snapshot['conditions']);$snapshot['active']=$active?1:0;
            $this->pdo->prepare('DELETE FROM rules WHERE id=?')->execute([$id]);
            $newId=$this->insertRule($snapshot,$conditions,true);
            if($userId!==null)$this->recordContribution((int)$snapshot['rule_set_id'],$userId);
            $this->pdo->commit();return $newId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function delete(int $id, ?int $userId = null): void
    {
        $this->assertDraftRule($id);
        $rule=$this->find($id); $stmt = $this->pdo->prepare('DELETE FROM rules WHERE id=?');
        $stmt->execute([$id]);
        if ($userId !== null) $this->recordContribution((int)$rule['rule_set_id'],$userId);
    }

    private function recordContribution(int $setId,int $userId): void { $this->pdo->prepare('INSERT IGNORE INTO rule_set_contributors(rule_set_id,user_id) VALUES(?,?)')->execute([$setId,$userId]);$this->pdo->prepare('UPDATE rule_sets SET last_modified_by=?,last_modified_at=NOW() WHERE id=?')->execute([$userId,$setId]); }

    private function draftSnapshot(int $id): array
    {
        $stmt=$this->pdo->prepare("SELECT r.* FROM rules r INNER JOIN rule_sets rs ON rs.id=r.rule_set_id WHERE r.id=? AND rs.status='DRAFT' FOR UPDATE");$stmt->execute([$id]);$rule=$stmt->fetch();
        if(!$rule)throw new \DomainException('Only Draft Rule Sets can be changed.');
        $stmt=$this->pdo->prepare('SELECT field_name,operator,value,sort_order FROM rule_conditions WHERE rule_id=? ORDER BY sort_order,id');$stmt->execute([$id]);$rule['conditions']=$stmt->fetchAll();return $rule;
    }

    private function insertRule(array $rule,array $conditions,bool $preserveSortOrder=false): int
    {
        $stmt=$this->pdo->prepare('INSERT INTO rules(rule_set_id,source_rule_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(?,?,?,?,?,?,?,?)');$stmt->execute([$rule['rule_set_id'],$rule['source_rule_id']??null,$rule['rule_code'],$rule['stage_name'],$rule['avg_actual_pd'],$rule['priority'],$rule['active'],$rule['description']]);$id=(int)$this->pdo->lastInsertId();
        $stmt=$this->pdo->prepare('INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(?,?,?,?,?)');foreach($conditions as $i=>$condition)$stmt->execute([$id,$condition['field_name'],$condition['operator'],$condition['value'],$preserveSortOrder?(int)$condition['sort_order']:$i+1]);return $id;
    }

    private function assertDraftRule(int $id): void { $stmt=$this->pdo->prepare('SELECT rule_set_id FROM rules WHERE id=?');$stmt->execute([$id]);$set=$stmt->fetchColumn();if(!$set) throw new \DomainException('Rule not found.');$this->assertDraftSet((int)$set); }
    private function assertDraftSet(int $id): void { $stmt=$this->pdo->prepare('SELECT status FROM rule_sets WHERE id=?');$stmt->execute([$id]);if($stmt->fetchColumn()!=='DRAFT') throw new \DomainException('Only Draft Rule Sets can be changed.'); }

    private function group(array $rows): array
    {
        $rules = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($rules[$id])) {
                $rules[$id] = $row;
                $rules[$id]['conditions'] = [];
            }
            if ($row['condition_id'] !== null) {
                $rules[$id]['conditions'][] = ['id'=>(int)$row['condition_id'],'field_name'=>$row['field_name'],'operator'=>$row['operator'],'value'=>$row['condition_value'],'sort_order'=>(int)$row['sort_order']];
            }
        }
        return array_values($rules);
    }
}
