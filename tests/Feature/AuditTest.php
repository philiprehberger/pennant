<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_create_emits_audit_event(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/flags', [
            'key' => 'a',
            'type' => 'bool',
            'default_value' => false,
        ], $this->authed($key));

        $resp = $this->getJson('/v1/audit', $this->authed($key));
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $resp->assertJsonPath('data.0.subject_type', 'flag');
        $resp->assertJsonPath('data.0.action', 'created');
        $resp->assertJsonPath('data.0.actor_type', 'api_key');
    }

    public function test_audit_log_captures_configuration_writes(): void
    {
        [$_w, $key] = $this->freshWorkspace();
        $flagId = $this->postJson('/v1/flags', ['key' => 'a', 'type' => 'bool', 'default_value' => false], $this->authed($key))->json('id');

        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => true,
        ], $this->authed($key));

        $events = $this->getJson('/v1/audit', $this->authed($key))->json('data');
        // Newest first
        $this->assertSame('configured', $events[0]['action']);
        $this->assertSame('flag_configuration', $events[0]['subject_type']);
    }
}
