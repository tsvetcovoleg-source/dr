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
                FROM rules r LEFT JOIN rule_conditions c ON c.rule_id = r.id
                WHERE r.active = 1
                ORDER BY FIELD(r.stage_name, 'HARD_REFUSAL_STAGE','RISK_REVIEW_STAGE','PORTFOLIO_SEGMENTATION_STAGE'), r.priority, r.id, c.sort_order, c.id";
        return $this->group($this->pdo->query($sql)->fetchAll());
    }

    public function all(): array
    {
        $sql = 'SELECT r.*, COUNT(c.id) condition_count FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id GROUP BY r.id ORDER BY FIELD(r.stage_name, ?, ?, ?), r.priority, r.id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(self::STAGES);
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, COUNT(c.id) condition_count FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id GROUP BY r.id ORDER BY r.updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function counts(): array
    {
        $row = $this->pdo->query("SELECT COUNT(*) total, SUM(active=1) active, SUM(stage_name='HARD_REFUSAL_STAGE') hard, SUM(stage_name='RISK_REVIEW_STAGE') review, SUM(stage_name='PORTFOLIO_SEGMENTATION_STAGE') segmentation FROM rules")->fetch();
        return array_map('intval', $row ?: []);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, c.id condition_id, c.field_name, c.operator, c.value condition_value, c.sort_order FROM rules r LEFT JOIN rule_conditions c ON c.rule_id=r.id WHERE r.id=? ORDER BY c.sort_order,c.id');
        $stmt->execute([$id]);
        $rules = $this->group($stmt->fetchAll());
        return $rules[0] ?? null;
    }

    public function save(array $rule, array $conditions, ?int $id = null): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE rules SET rule_code=?,stage_name=?,avg_actual_pd=?,priority=?,active=?,description=? WHERE id=?');
                $stmt->execute([$rule['rule_code'],$rule['stage_name'],$rule['avg_actual_pd'],$rule['priority'],$rule['active'],$rule['description'],$id]);
                $this->pdo->prepare('DELETE FROM rule_conditions WHERE rule_id=?')->execute([$id]);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO rules(rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(?,?,?,?,?,?)');
                $stmt->execute([$rule['rule_code'],$rule['stage_name'],$rule['avg_actual_pd'],$rule['priority'],$rule['active'],$rule['description']]);
                $id = (int) $this->pdo->lastInsertId();
            }
            $stmt = $this->pdo->prepare('INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(?,?,?,?,?)');
            foreach ($conditions as $i => $condition) {
                $stmt->execute([$id,$condition['field_name'],$condition['operator'],$condition['value'],$i + 1]);
            }
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE rules SET active=? WHERE id=?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rules WHERE id=?');
        $stmt->execute([$id]);
    }

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
