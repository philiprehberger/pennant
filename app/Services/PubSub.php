<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Workspace;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Publishes flag-change events to Redis pub/sub and to a per-environment ring
 * buffer used for Last-Event-ID replay.
 *
 * Channel:        flag-updates:{workspace_id}:{environment_key}
 * Ring buffer:    flag-events:{workspace_id}:{environment_key} (capped at 100 events)
 *
 * The SSE broadcaster (infra/sse-broadcaster) subscribes to the channel and
 * scans the ring buffer to replay events newer than a supplied Last-Event-ID.
 */
final class PubSub
{
    private const RING_BUFFER_CAP = 100;

    public static function publishFlagChange(
        Workspace $workspace,
        Environment $environment,
        string $flagKey,
        string $action = 'changed',
    ): string {
        $event = [
            'id' => (string) Str::ulid(),
            'type' => "flag.{$action}",
            'flag_key' => $flagKey,
            'environment' => $environment->key,
            'workspace_id' => $workspace->id,
            'timestamp' => now()->toIso8601String(),
        ];

        $channel = self::channel($workspace, $environment);
        $bufferKey = self::ringBufferKey($workspace, $environment);
        $payload = json_encode($event, JSON_THROW_ON_ERROR);

        try {
            Redis::publish($channel, $payload);
            Redis::lpush($bufferKey, $payload);
            Redis::ltrim($bufferKey, 0, self::RING_BUFFER_CAP - 1);
        } catch (\Throwable $e) {
            // Redis can be unavailable in local dev or tests. Don't fail
            // mutations because the broadcaster is offline; the SDK will
            // pick up changes via its polling fallback.
            logger()->warning('PubSub publish failed', ['error' => $e->getMessage(), 'channel' => $channel]);
        }

        return $event['id'];
    }

    public static function channel(Workspace $workspace, Environment $environment): string
    {
        return config('app.pennant_pubsub_prefix', 'flag-updates').":{$workspace->id}:{$environment->key}";
    }

    public static function ringBufferKey(Workspace $workspace, Environment $environment): string
    {
        return "flag-events:{$workspace->id}:{$environment->key}";
    }
}
