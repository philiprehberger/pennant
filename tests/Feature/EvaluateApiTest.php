<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_returns_default_when_no_configuration(): void
    {
        [$_w, $key] = $this->freshWorkspace();
        $this->postJson('/v1/flags', [
            'key' => 'new-feature',
            'type' => 'bool',
            'default_value' => false,
        ], $this->authed($key));

        $resp = $this->postJson('/v1/evaluate', [
            'environment' => 'prod',
            'context' => ['userId' => 'alice'],
            'flags' => ['new-feature'],
        ], $this->authed($key));

        $resp->assertOk();
        $resp->assertJsonPath('new-feature.value', false);
        $resp->assertJsonPath('new-feature.reason', 'default');
    }

    public function test_evaluate_returns_off_state_default(): void
    {
        [$_w, $key] = $this->freshWorkspace();
        $flagId = $this->postJson('/v1/flags', ['key' => 'f', 'type' => 'bool', 'default_value' => false], $this->authed($key))->json('id');
        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'off',
            'variation' => true,
        ], $this->authed($key));

        $resp = $this->postJson('/v1/evaluate', [
            'environment' => 'prod',
            'context' => ['userId' => 'alice'],
        ], $this->authed($key));

        $resp->assertJsonPath('f.value', false);
        $resp->assertJsonPath('f.reason', 'off');
    }

    public function test_evaluate_matches_targeting_rule(): void
    {
        [$_w, $key] = $this->freshWorkspace();
        $flagId = $this->postJson('/v1/flags', ['key' => 'cta', 'type' => 'string', 'default_value' => 'Get started'], $this->authed($key))->json('id');

        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => 'Try free trial',
            'rules' => [
                [
                    'priority' => 0,
                    'condition' => ['attribute' => 'plan', 'op' => 'equals', 'value' => 'enterprise'],
                    'variation' => 'Talk to sales',
                ],
            ],
        ], $this->authed($key));

        $rule = $this->postJson('/v1/evaluate', [
            'environment' => 'prod',
            'context' => ['userId' => 'alice', 'plan' => 'enterprise'],
        ], $this->authed($key));
        $rule->assertJsonPath('cta.value', 'Talk to sales');
        $rule->assertJsonPath('cta.reason', 'rule_match');

        $fall = $this->postJson('/v1/evaluate', [
            'environment' => 'prod',
            'context' => ['userId' => 'bob', 'plan' => 'hobby'],
        ], $this->authed($key));
        $fall->assertJsonPath('cta.value', 'Try free trial');
        $fall->assertJsonPath('cta.reason', 'fallthrough');
    }

    public function test_evaluate_percentage_rollout_is_deterministic(): void
    {
        [$_w, $key] = $this->freshWorkspace();
        $flagId = $this->postJson('/v1/flags', ['key' => 'percent', 'type' => 'bool', 'default_value' => false], $this->authed($key))->json('id');

        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => false,
            'rules' => [
                [
                    'priority' => 0,
                    'condition' => ['attribute' => 'userId', 'op' => 'percentage', 'value' => 100],
                    'variation' => true,
                ],
            ],
        ], $this->authed($key));

        $resp = $this->postJson('/v1/evaluate', [
            'environment' => 'prod',
            'context' => ['userId' => 'anyone'],
        ], $this->authed($key));
        $resp->assertJsonPath('percent.value', true);
        $resp->assertJsonPath('percent.reason', 'percentage_rollout');
    }
}
