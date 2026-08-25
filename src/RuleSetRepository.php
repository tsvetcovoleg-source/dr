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

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT rs.*, u.username created_by_username, COUNT(r.id) rule_count FROM rule_sets rs LEFT JOIN users u ON u.id=rs.created_by LEFT JOIN rules r ON r.rule_set_id=rs.id WHERE rs.id=? GROUP BY rs.id');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT rs.*, u.username created_by_username, COUNT(r.id) rule_count FROM rule_sets rs LEFT JOIN users u ON u.id=rs.created_by LEFT JOIN rules r ON r.rule_set_id=rs.id GROUP BY rs.id ORDER BY rs.version DESC')->fetchAll();
    }

    public function createDraftFromActive(int $userId, ?string $name, ?string $comment): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($this->pdo->query("SELECT id FROM rule_sets WHERE status='DRAFT' LIMIT 1 FOR UPDATE")->fetch()) {
                throw new DomainException('A Draft Rule Set already exists.');
            }
            $active = $this->pdo->query("SELECT id FROM rule_sets WHERE status='ACTIVE' LIMIT 1 FOR UPDATE")->fetch();
            if (!$active) throw new DomainException('No Active Rule Set exists.');
            $version = (int)$this->pdo->query('SELECT COALESCE(MAX(version),0)+1 FROM rule_sets FOR UPDATE')->fetchColumn();
            $stmt=$this->pdo->prepare("INSERT INTO rule_sets(version,name,status,created_by,comment) VALUES(?,?,'DRAFT',?,?)");
            $stmt->execute([$version, $name ?: null, $userId, $comment ?: null]);
            $draftId=(int)$this->pdo->lastInsertId();
            $rules=$this->pdo->prepare('SELECT * FROM rules WHERE rule_set_id=? ORDER BY id'); $rules->execute([(int)$active['id']]);
            $insertRule=$this->pdo->prepare('INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(?,?,?,?,?,?,?)');
            $conditions=$this->pdo->prepare('SELECT field_name,operator,value,sort_order FROM rule_conditions WHERE rule_id=? ORDER BY sort_order,id');
            $insertCondition=$this->pdo->prepare('INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(?,?,?,?,?)');
            foreach($rules->fetchAll() as $rule){
                $insertRule->execute([$draftId,$rule['rule_code'],$rule['stage_name'],$rule['avg_actual_pd'],$rule['priority'],$rule['active'],$rule['description']]);
                $newRuleId=(int)$this->pdo->lastInsertId(); $conditions->execute([(int)$rule['id']]);
                foreach($conditions->fetchAll() as $condition) $insertCondition->execute([$newRuleId,$condition['field_name'],$condition['operator'],$condition['value'],$condition['sort_order']]);
            }
            $this->pdo->commit(); return $draftId;
        } catch(Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function byStatus(string $status): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM rule_sets WHERE status=? ORDER BY version DESC LIMIT 1'); $stmt->execute([$status]);
        return $stmt->fetch() ?: null;
    }
}
