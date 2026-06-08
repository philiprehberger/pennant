<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Flag;
use App\Models\FlagConfiguration;

/**
 * Top-level evaluation: given a flag and a context, produce
 * { value, reason, ruleId }.
 */
final class FlagEvaluator
{
    public function __construct(private readonly RuleEvaluator $ruleEvaluator)
    {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{value: mixed, reason: string, rule_id: ?string}
     */
    public function evaluate(Flag $flag, Environment $env, array $context): array
    {
        $config = $flag->configurations()->where('environment_id', $env->id)->with('rules')->first();

        if ($config === null) {
            return [
                'value' => $flag->default_value['v'] ?? null,
                'reason' => 'default',
                'rule_id' => null,
            ];
        }

        if ($config->state === FlagConfiguration::STATE_OFF) {
            return [
                'value' => $flag->default_value['v'] ?? null,
                'reason' => 'off',
                'rule_id' => null,
            ];
        }

        $evaluator = $this->ruleEvaluator->withBucketing(
            flagKey: $flag->key,
            attribute: $config->bucketing_attribute,
            seed: $config->bucketing_seed,
        );

        foreach ($config->rules as $rule) {
            if ($evaluator->evaluate($rule->condition, $context)) {
                return [
                    'value' => $rule->variation['v'] ?? null,
                    'reason' => $this->ruleReason($rule->condition),
                    'rule_id' => $rule->id,
                ];
            }
        }

        return [
            'value' => $config->variation['v'] ?? null,
            'reason' => 'fallthrough',
            'rule_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function ruleReason(array $condition): string
    {
        // A rule whose condition mentions `percentage` somewhere is reported
        // as a percentage rollout; everything else as a plain rule match.
        $json = json_encode($condition);

        return $json !== false && str_contains($json, '"percentage"') ? 'percentage_rollout' : 'rule_match';
    }
}
