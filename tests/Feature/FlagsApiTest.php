<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_list_flags(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/flags', [
            'key' => 'new-checkout',
            'type' => 'bool',
            'description' => 'Roll out the new checkout flow.',
            'default_value' => false,
        ], $this->authed($key))->assertCreated();

        $resp = $this->getJson('/v1/flags', $this->authed($key));
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('new-checkout', $resp->json('data.0.key'));
    }

    public function test_create_flag_rejects_value_type_mismatch(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/flags', [
            'key' => 'limit',
            'type' => 'number',
            'default_value' => 'not-a-number',
        ], $this->authed($key))->assertStatus(400);
    }

    public function test_create_flag_rejects_invalid_key(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/flags', [
            'key' => 'Bad Key',
            'type' => 'bool',
            'default_value' => false,
        ], $this->authed($key))->assertStatus(400);
    }

    public function test_archive_flag(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $id = $this->postJson('/v1/flags', [
            'key' => 'temp',
            'type' => 'bool',
            'default_value' => false,
        ], $this->authed($key))->json('id');

        $this->deleteJson("/v1/flags/{$id}", [], $this->authed($key))->assertNoContent();

        $this->getJson('/v1/flags', $this->authed($key))
            ->assertJsonPath('data', []);
    }

    public function test_configure_flag_for_environment(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $flagId = $this->postJson('/v1/flags', [
            'key' => 'cta-copy',
            'type' => 'string',
            'default_value' => 'Get started',
        ], $this->authed($key))->json('id');

        $resp = $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => 'Start your free trial',
            'rules' => [
                [
                    'priority' => 0,
                    'description' => 'Enterprise customers see legacy copy',
                    'condition' => ['attribute' => 'plan', 'op' => 'equals', 'value' => 'enterprise'],
                    'variation' => 'Get started',
                ],
            ],
        ], $this->authed($key));

        $resp->assertOk();
        $resp->assertJsonPath('state', 'on');
        $resp->assertJsonPath('variation', 'Start your free trial');
        $resp->assertJsonPath('environment_key', 'prod');
        $this->assertCount(1, $resp->json('rules'));
    }

    public function test_configuration_rejects_variation_type_mismatch(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $flagId = $this->postJson('/v1/flags', [
            'key' => 'admin-only',
            'type' => 'bool',
            'default_value' => false,
        ], $this->authed($key))->json('id');

        $this->putJson("/v1/flags/{$flagId}/configurations/dev", [
            'state' => 'on',
            'variation' => 'not-a-bool',
        ], $this->authed($key))->assertStatus(400);
    }
}
