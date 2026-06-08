<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Services\PubSub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PubSubTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_change_publishes_and_buffers(): void
    {
        // Predis driver will try to connect on first use; skip if no Redis.
        if (! $this->redisAvailable()) {
            $this->markTestSkipped('Redis not available locally.');
        }

        [$workspace] = $this->freshWorkspace();
        /** @var Environment $env */
        $env = $workspace->environments()->where('key', 'prod')->first();

        $bufferKey = PubSub::ringBufferKey($workspace, $env);
        Redis::del($bufferKey);

        PubSub::publishFlagChange($workspace, $env, 'sample-flag');

        $entries = Redis::lrange($bufferKey, 0, -1);
        $this->assertCount(1, $entries);
        $entry = json_decode($entries[0], true);
        $this->assertSame('flag.changed', $entry['type']);
        $this->assertSame('sample-flag', $entry['flag_key']);
    }

    public function test_publish_silently_succeeds_when_redis_down(): void
    {
        [$workspace] = $this->freshWorkspace();
        $env = $workspace->environments()->where('key', 'prod')->first();

        // The PubSub helper logs warnings but does not throw on Redis errors.
        $id = PubSub::publishFlagChange($workspace, $env, 'sample-flag');
        $this->assertNotEmpty($id);
    }

    private function redisAvailable(): bool
    {
        try {
            return Redis::connection()->ping() !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
