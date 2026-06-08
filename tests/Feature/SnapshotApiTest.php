<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_snapshot_returns_full_rules(): void
    {
        [$workspace, $key] = $this->freshWorkspace();
        $flagId = $this->postJson('/v1/flags', ['key' => 'a', 'type' => 'bool', 'default_value' => false], $this->authed($key))->json('id');
        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => true,
            'rules' => [
                ['priority' => 0, 'condition' => ['attribute' => 'plan', 'op' => 'equals', 'value' => 'free'], 'variation' => false],
            ],
        ], $this->authed($key));

        $resp = $this->getJson('/v1/snapshot?environment=prod', $this->authed($key));
        $resp->assertOk();
        $resp->assertJsonPath('kind', 'server');
        $resp->assertJsonPath('flags.0.key', 'a');
        $resp->assertJsonPath('flags.0.configuration.state', 'on');
        $this->assertNotEmpty($resp->json('flags.0.configuration.rules'));
    }

    public function test_client_snapshot_returns_pre_evaluated_values(): void
    {
        [$workspace, $serverKey] = $this->freshWorkspace();
        $flagId = $this->postJson('/v1/flags', ['key' => 'cta', 'type' => 'string', 'default_value' => 'a'], $this->authed($serverKey))->json('id');
        $this->putJson("/v1/flags/{$flagId}/configurations/prod", [
            'state' => 'on',
            'variation' => 'b',
        ], $this->authed($serverKey));

        [, $clientKey] = ApiKey::mint($workspace, ApiKey::KIND_CLIENT);
        $context = base64_encode(json_encode(['userId' => 'alice']));
        $context = rtrim(strtr($context, '+/', '-_'), '=');

        $resp = $this->getJson("/v1/snapshot?environment=prod&context={$context}", $this->authed($clientKey));
        $resp->assertOk();
        $resp->assertJsonPath('kind', 'client');
        $resp->assertJsonPath('flags.0.value', 'b');
        $resp->assertJsonMissingPath('flags.0.configuration');
    }

    public function test_client_snapshot_requires_context(): void
    {
        [$workspace, $serverKey] = $this->freshWorkspace();
        [, $clientKey] = ApiKey::mint($workspace, ApiKey::KIND_CLIENT);

        $this->getJson('/v1/snapshot?environment=prod', $this->authed($clientKey))->assertStatus(400);
    }
}
