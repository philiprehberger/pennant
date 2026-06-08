<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_use_segment(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/segments', [
            'key' => 'beta-users',
            'name' => 'Beta users',
            'condition' => ['attribute' => 'email', 'op' => 'ends_with', 'value' => '@beta.example'],
        ], $this->authed($key))->assertCreated();

        $flagId = $this->postJson('/v1/flags', ['key' => 'beta-feature', 'type' => 'bool', 'default_value' => false], $this->authed($key))->json('id');
        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => false,
            'rules' => [['priority' => 0, 'condition' => ['segment' => 'beta-users'], 'variation' => true]],
        ], $this->authed($key))->assertOk();

        $resp = $this->postJson('/v1/evaluate', [
            'environment' => 'prod',
            'context' => ['email' => 'alice@beta.example'],
        ], $this->authed($key));
        $resp->assertJsonPath('beta-feature.value', true);
    }

    public function test_cycle_detection_rejects_self_reference(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/segments', [
            'key' => 'looper',
            'name' => 'Looper',
            'condition' => ['segment' => 'looper'],
        ], $this->authed($key))->assertStatus(400);
    }

    public function test_cycle_detection_rejects_two_step_loop(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/segments', [
            'key' => 'a',
            'name' => 'A',
            'condition' => ['attribute' => 'plan', 'op' => 'equals', 'value' => 'enterprise'],
        ], $this->authed($key))->assertCreated();

        $this->postJson('/v1/segments', [
            'key' => 'b',
            'name' => 'B',
            'condition' => ['segment' => 'a'],
        ], $this->authed($key))->assertCreated();

        $aId = $this->getJson('/v1/segments', $this->authed($key))->json('data.0.id');

        $this->patchJson("/v1/segments/{$aId}", [
            'condition' => ['segment' => 'b'],
        ], $this->authed($key))->assertStatus(400);
    }
}
