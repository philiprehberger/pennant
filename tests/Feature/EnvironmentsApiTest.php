<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_default_environments(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $resp = $this->getJson('/v1/environments', $this->authed($key));
        $resp->assertOk();
        $this->assertCount(3, $resp->json('data'));
    }

    public function test_create_environment(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $resp = $this->postJson('/v1/environments', [
            'key' => 'qa',
            'name' => 'Quality Assurance',
        ], $this->authed($key));

        $resp->assertCreated();
        $resp->assertJsonPath('key', 'qa');
        $resp->assertJsonPath('production', false);
    }

    public function test_duplicate_environment_key_rejected(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $this->postJson('/v1/environments', ['key' => 'prod', 'name' => 'Production'], $this->authed($key))
            ->assertStatus(409);
    }

    public function test_update_environment(): void
    {
        [$_w, $key] = $this->freshWorkspace();

        $envId = $this->getJson('/v1/environments', $this->authed($key))->json('data.0.id');

        $this->patchJson("/v1/environments/{$envId}", ['name' => 'Renamed Dev'], $this->authed($key))
            ->assertOk()
            ->assertJsonPath('name', 'Renamed Dev');
    }

    public function test_unauthorized_without_key(): void
    {
        $this->getJson('/v1/environments')->assertStatus(401);
    }
}
