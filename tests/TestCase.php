<?php

namespace Tests;

use App\Models\ApiKey;
use App\Models\Environment;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Build a workspace with a server key + three standard environments.
     *
     * @return array{0: Workspace, 1: string, 2: array<string, Environment>} [workspace, server-key plaintext, envs]
     */
    protected function freshWorkspace(string $name = 'Test Workspace'): array
    {
        $workspace = Workspace::create(['name' => $name, 'slug' => str()->slug($name).'-'.uniqid()]);

        $envs = [];
        foreach (['dev' => false, 'staging' => false, 'prod' => true] as $key => $production) {
            $envs[$key] = $workspace->environments()->create([
                'key' => $key,
                'name' => ucfirst($key),
                'production' => $production,
            ]);
        }

        [, $plaintext] = ApiKey::mint($workspace, ApiKey::KIND_SERVER, 'live');

        return [$workspace, $plaintext, $envs];
    }

    /** @return array<string, string> */
    protected function authed(string $key): array
    {
        return ['Authorization' => 'Bearer '.$key, 'Accept' => 'application/json'];
    }
}
