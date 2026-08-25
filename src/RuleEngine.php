<?php
declare(strict_types=1);

namespace DecisionRules;

final class RuleEngine
{
    public function __construct(private RuleRepository $repository) {}

    public function evaluate(array $input): array
    {
        $started = hrtime(true);
        $rules = $this->repository->activeRules();
        $ruleSet = $this->repository->activeRuleSet();
        if (!$ruleSet) throw new \RuntimeException('No Active Rule Set exists.');
        $missing = [];
        $matchedByStage = array_fill_keys(RuleRepository::STAGES, []);

        foreach ($rules as $rule) {
            $matched = count($rule['conditions']) > 0;
            foreach ($rule['conditions'] as $condition) {
                $field = $condition['field_name'];
                if (!array_key_exists($field, $input)) {
                    $missing[$field] = true;
                    $matched = false;
                    continue;
                }
                if (!$this->matches($input[$field], $condition['operator'], $condition['value'])) {
                    $matched = false;
                }
            }
            if ($matched) {
                $matchedByStage[$rule['stage_name']][] = $this->present($rule);
            }
        }

        $decision = 'NO_MATCH';
        $stage = null;
        $matches = [];
        foreach ([
            'HARD_REFUSAL_STAGE' => 'DECLINE',
            'RISK_REVIEW_STAGE' => 'REVIEW',
            'PORTFOLIO_SEGMENTATION_STAGE' => 'APPROVE',
        ] as $candidate => $candidateDecision) {
            if ($matchedByStage[$candidate]) {
                $decision = $candidateDecision;
                $stage = $candidate;
                $matches = $matchedByStage[$candidate];
                break;
            }
        }

        return [
            'success' => true,
            'decision' => $decision,
            'stage' => $stage,
            'rule_set' => ['id'=>(int)$ruleSet['id'], 'version'=>(int)$ruleSet['version']],
            'matched_rules' => $matches,
            'missing_fields' => array_keys($missing),
            'meta' => ['rules_checked' => count($rules), 'execution_time_ms' => round((hrtime(true) - $started) / 1e6, 2)],
        ];
    }

    public function matches(mixed $actual, string $operator, ?string $expected): bool
    {
        if (is_array($actual)) {
            return false;
        }
        if ($operator === 'IS_NULL') return $actual === null || $actual === '';
        if ($operator === 'IS_NOT_NULL') return $actual !== null && $actual !== '';
        if ($operator === 'IN' || $operator === 'NOT_IN') {
            $values = $this->listValue($expected);
            $found = in_array((string) $actual, array_map('strval', $values), true);
            return $operator === 'IN' ? $found : !$found;
        }
        if (in_array($operator, ['>','>=','<','<='], true)) {
            if (!is_numeric($actual) || !is_numeric($expected)) return false;
            $a = (float) $actual; $b = (float) $expected;
            return match ($operator) {'>' => $a > $b, '>=' => $a >= $b, '<' => $a < $b, '<=' => $a <= $b};
        }
        if ($operator === '=') return is_numeric($actual) && is_numeric($expected) ? (float)$actual === (float)$expected : (string)$actual === (string)$expected;
        if ($operator === '!=') return is_numeric($actual) && is_numeric($expected) ? (float)$actual !== (float)$expected : (string)$actual !== (string)$expected;
        return false;
    }

    private function listValue(?string $value): array
    {
        if ($value === null) return [];
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return $decoded;
        return [$value];
    }

    private function present(array $rule): array
    {
        return [
            'rule_code' => $rule['rule_code'],
            'avg_actual_pd' => round((float) $rule['avg_actual_pd'], 2),
            'priority' => (int) $rule['priority'],
        ];
    }
}
