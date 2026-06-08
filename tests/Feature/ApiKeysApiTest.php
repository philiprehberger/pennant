<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeysApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mint_server_key_returns_plaintext_once(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $resp = $this->postJson('/v1/api-keys', [
            'name' => 'CI',
            'kind' => 'server',
        ], $this->authed($key));

        $resp->assertCreated();
        $resp->assertJsonPath('kind', 'server');
        $resp->assertJsonStructure(['id', 'secret', 'prefix', 'last_four']);
        $this->assertStringStartsWith('pn_srv_live_', $resp->json('secret'));
    }

    public function test_mint_client_key_uses_client_prefix(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $resp = $this->postJson('/v1/api-keys', [
            'name' => 'Browser SDK',
            'kind' => 'client',
            'environment_key' => 'prod',
        ], $this->authed($key));

        $resp->assertCreated();
        $resp->assertJsonPath('kind', 'client');
        $resp->assertJsonPath('environment_key', 'prod');
        $this->assertStringStartsWith('pn_clt_live_', $resp->json('secret'));
    }

    public function test_client_key_cannot_mint_keys(): void
    {
        [$workspace] = $this->freshWorkspace();
        [, $clientPlaintext] = \App\Models\ApiKey::mint($workspace, \App\Models\ApiKey::KIND_CLIENT);

        $this->postJson('/v1/api-keys', [
            'kind' => 'server',
        ], $this->authed($clientPlaintext))->assertStatus(403);
    }

    public function test_revoke_key_makes_it_unusable(): void
    {
        [$_w, $key] = $this->freshWorkspace();
        $created = $this->postJson('/v1/api-keys', ['kind' => 'server'], $this->authed($key))->json();

        $this->deleteJson('/v1/api-keys/'.$created['id'], [], $this->authed($key))->assertNoContent();

        $this->getJson('/v1/flags', $this->authed($created['secret']))->assertStatus(401);
    }

    public function test_invalid_key_rejected(): void
    {
        $this->getJson('/v1/flags', $this->authed('pn_srv_live_invalid'))->assertStatus(401);
    }
}
