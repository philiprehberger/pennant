<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Workspace;
use App\Services\FlagEvaluator;
use App\Services\RuleEvaluator;
use App\Services\SegmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SnapshotController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $envKey = (string) $request->query('environment', '');
        if ($envKey === '') {
            return $this->problem(400, 'environment query parameter is required.');
        }

        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $env = $workspace->environments()->where('key', $envKey)->firstOrFail();

        // Client keys: require a base64url(JSON) context query and emit
        // pre-evaluated values only. Server keys: emit raw rules.
        if ($apiKey->isClient()) {
            $context = $this->decodeContext($request->query('context'));
            if ($context === null) {
                return $this->problem(400, 'context query parameter is required for client keys (base64url(JSON)).');
            }

            return response()->json($this->buildClientSnapshot($workspace, $env, $context));
        }

        return response()->json($this->buildServerSnapshot($workspace, $env));
    }

    private function buildServerSnapshot(Workspace $workspace, $env): array
    {
        $flags = $workspace->flags()
            ->whereNull('archived_at')
            ->with(['configurations' => function ($q) use ($env) {
                $q->where('environment_id', $env->id)->with('rules');
            }])
            ->get();

        return [
            'environment' => $env->key,
            'version' => (string) Str::ulid(),
            'kind' => 'server',
            'flags' => $flags->map(function ($flag) {
                $config = $flag->configurations->first();

                return [
                    'key' => $flag->key,
                    'type' => $flag->type,
                    'default_value' => $flag->default_value['v'] ?? null,
                    'configuration' => $config ? [
                        'state' => $config->state,
                        'variation' => $config->variation['v'] ?? null,
                        'bucketing_attribute' => $config->bucketing_attribute,
                        'bucketing_seed' => $config->bucketing_seed,
                        'rules' => $config->rules->map(fn ($r) => [
                            'priority' => $r->priority,
                            'condition' => $r->condition,
                            'variation' => $r->variation['v'] ?? null,
                        ])->all(),
                    ] : null,
                ];
            })->all(),
            'segments' => $workspace->segments()->get()->map(fn ($s) => [
                'key' => $s->key,
                'name' => $s->name,
                'condition' => $s->condition,
            ])->all(),
        ];
    }

    private function buildClientSnapshot(Workspace $workspace, $env, array $context): array
    {
        $flags = $workspace->flags()->whereNull('archived_at')->get();
        $evaluator = new FlagEvaluator(new RuleEvaluator((new SegmentResolver($workspace))->asCallable()));

        return [
            'environment' => $env->key,
            'version' => (string) Str::ulid(),
            'kind' => 'client',
            'flags' => $flags->map(function ($flag) use ($evaluator, $env, $context) {
                $result = $evaluator->evaluate($flag, $env, $context);

                return [
                    'key' => $flag->key,
                    'type' => $flag->type,
                    'value' => $result['value'],
                    'reason' => $result['reason'],
                ];
            })->all(),
        ];
    }

    private function decodeContext(mixed $raw): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $padded = strtr($raw, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $json = base64_decode($padded, true);
        if ($json === false) {
            return null;
        }
        $decoded = json_decode($json, associative: true);

        return is_array($decoded) ? $decoded : null;
    }

    private function problem(int $status, string $detail): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Invalid request',
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
