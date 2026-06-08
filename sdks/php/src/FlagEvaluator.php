<?php

declare(strict_types=1);

namespace Pennant;

final class FlagEvaluator
{
    public function __construct(private readonly RuleEvaluator $ruleEvaluator)
    {
    }

    /**
     * @param  array<string, mixed>  $flag      shape from /v1/snapshot
     * @param  array<string, mixed>  $context
     * @return array{value: mixed, reason: string, rule_index: ?int}
     */
    public function evaluate(array $flag, array $context): array
    {
        $config = $flag['configuration'] ?? null;
        $default = $flag['default_value'] ?? null;

        if (! is_array($config)) {
            return ['value' => $default, 'reason' => 'default', 'rule_index' => null];
        }
        if (($config['state'] ?? 'off') === 'off') {
            return ['value' => $default, 'reason' => 'off', 'rule_index' => null];
        }

        $evaluator = $this->ruleEvaluator->withBucketing(
            flagKey: $flag['key'] ?? '',
            attribute: $config['bucketing_attribute'] ?? 'userId',
            seed: $config['bucketing_seed'] ?? '',
        );

        $rules = $config['rules'] ?? [];
        $idx = 0;
        foreach ($rules as $rule) {
            $condition = $rule['condition'] ?? [];
            if ($evaluator->evaluate($condition, $context)) {
                $reason = str_contains((string) json_encode($condition), '"percentage"')
                    ? 'percentage_rollout'
                    : 'rule_match';
                return ['value' => $rule['variation'] ?? null, 'reason' => $reason, 'rule_index' => $idx];
            }
            $idx++;
        }

        return [
            'value' => $config['variation'] ?? null,
            'reason' => 'fallthrough',
            'rule_index' => null,
        ];
    }
}
