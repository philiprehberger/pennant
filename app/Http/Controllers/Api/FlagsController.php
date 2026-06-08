<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use App\Models\FlagConfiguration;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\PubSub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlagsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $limit = (int) $request->query('limit', 25);
        $limit = max(1, min(100, $limit));
        $cursor = $request->query('cursor');

        $query = $workspace->flags()->whereNull('archived_at')->orderBy('id');
        if ($cursor) {
            $query->where('id', '>', $cursor);
        }
        $flags = $query->limit($limit + 1)->get();

        $hasMore = $flags->count() > $limit;
        $page = $flags->take($limit);

        return response()->json([
            'data' => $page->map(fn ($f) => $this->serialize($f))->all(),
            'next_cursor' => $hasMore ? $page->last()->id : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        $data = Validator::make($request->all(), [
            'key' => ['required', 'string', 'min:1', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'type' => ['required', 'in:bool,string,number,json'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_value' => ['required'],
        ])->validate();

        if ($workspace->flags()->where('key', $data['key'])->exists()) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Conflict',
                'status' => 409,
                'detail' => "A flag with key '{$data['key']}' already exists in this workspace.",
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        if (! $this->valueMatchesType($data['default_value'], $data['type'])) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Invalid request',
                'status' => 400,
                'detail' => "default_value does not match declared type '{$data['type']}'.",
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        $flag = $workspace->flags()->create([
            'key' => $data['key'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'default_value' => ['v' => $data['default_value']],
        ]);

        AuditLogger::record($workspace, 'flag', $flag->id, 'created', [
            'after' => ['key' => $flag->key, 'type' => $flag->type, 'default_value' => $data['default_value']],
        ], request: $request);

        return response()->json($this->serialize($flag), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $flag = $this->find($request, $id);

        return response()->json($this->serialize($flag, withConfigurations: true));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $flag = $this->find($request, $id);

        $data = Validator::make($request->all(), [
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_value' => ['sometimes'],
        ])->validate();

        if (array_key_exists('default_value', $data)) {
            if (! $this->valueMatchesType($data['default_value'], $flag->type)) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Invalid request',
                    'status' => 400,
                    'detail' => "default_value does not match the flag's type '{$flag->type}'.",
                ], 400, ['Content-Type' => 'application/problem+json']);
            }
            $flag->default_value = ['v' => $data['default_value']];
        }
        if (array_key_exists('description', $data)) {
            $flag->description = $data['description'];
        }
        $flag->save();

        return response()->json($this->serialize($flag));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $flag = $this->find($request, $id);
        $flag->archived_at = now();
        $flag->save();

        AuditLogger::record($this->workspace($request), 'flag', $flag->id, 'archived', [], request: $request);

        return response()->json(status: 204);
    }

    public function getConfiguration(Request $request, string $id, string $environmentKey): JsonResponse
    {
        $flag = $this->find($request, $id);
        $config = $this->resolveConfiguration($flag, $environmentKey, createIfMissing: true);

        return response()->json($this->serializeConfiguration($config));
    }

    public function putConfiguration(Request $request, string $id, string $environmentKey): JsonResponse
    {
        $flag = $this->find($request, $id);
        $config = $this->resolveConfiguration($flag, $environmentKey, createIfMissing: true);

        $data = Validator::make($request->all(), [
            'state' => ['required', 'in:on,off'],
            'variation' => ['required'],
            'bucketing_attribute' => ['sometimes', 'string', 'max:64'],
            'rules' => ['sometimes', 'array'],
            'rules.*.priority' => ['required_with:rules', 'integer', 'min:0'],
            'rules.*.description' => ['nullable', 'string', 'max:200'],
            'rules.*.condition' => ['required_with:rules', 'array'],
            'rules.*.variation' => ['required_with:rules'],
        ])->validate();

        if (! $this->valueMatchesType($data['variation'], $flag->type)) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Invalid request',
                'status' => 400,
                'detail' => "variation does not match the flag's type '{$flag->type}'.",
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        foreach ($data['rules'] ?? [] as $r) {
            if (! $this->valueMatchesType($r['variation'], $flag->type)) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Invalid request',
                    'status' => 400,
                    'detail' => "rule variation does not match the flag's type '{$flag->type}'.",
                ], 400, ['Content-Type' => 'application/problem+json']);
            }
        }

        $config->state = $data['state'];
        $config->variation = ['v' => $data['variation']];
        if (isset($data['bucketing_attribute'])) {
            $config->bucketing_attribute = $data['bucketing_attribute'];
        }
        $config->save();

        $config->rules()->delete();
        foreach ($data['rules'] ?? [] as $r) {
            $config->rules()->create([
                'priority' => $r['priority'],
                'description' => $r['description'] ?? null,
                'condition' => $r['condition'],
                'variation' => ['v' => $r['variation']],
            ]);
        }

        AuditLogger::record($this->workspace($request), 'flag_configuration', $config->id, 'configured', [
            'flag_id' => $flag->id,
            'environment_key' => $environmentKey,
            'state' => $config->state,
            'variation' => $data['variation'],
            'rule_count' => count($data['rules'] ?? []),
        ], request: $request);

        PubSub::publishFlagChange($this->workspace($request), $config->environment, $flag->key);

        return response()->json($this->serializeConfiguration($config->fresh('rules')));
    }

    private function resolveConfiguration(Flag $flag, string $environmentKey, bool $createIfMissing): FlagConfiguration
    {
        $env = $flag->workspace->environments()->where('key', $environmentKey)->firstOrFail();

        return FlagConfiguration::firstOrCreate(
            ['flag_id' => $flag->id, 'environment_id' => $env->id],
            ['state' => FlagConfiguration::STATE_OFF, 'variation' => ['v' => $this->valueOf($flag->default_value)]],
        );
    }

    private function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }

    private function find(Request $request, string $id): Flag
    {
        return $this->workspace($request)->flags()->whereNull('archived_at')->findOrFail($id);
    }

    private function serialize(Flag $flag, bool $withConfigurations = false): array
    {
        $out = [
            'id' => $flag->id,
            'key' => $flag->key,
            'type' => $flag->type,
            'description' => $flag->description,
            'default_value' => $this->valueOf($flag->default_value),
            'archived_at' => $flag->archived_at?->toIso8601String(),
            'created_at' => $flag->created_at?->toIso8601String(),
        ];

        if ($withConfigurations) {
            $out['configurations'] = $flag->configurations()
                ->with(['environment', 'rules'])
                ->get()
                ->map(fn ($c) => $this->serializeConfiguration($c))
                ->all();
        }

        return $out;
    }

    private function serializeConfiguration(FlagConfiguration $config): array
    {
        return [
            'id' => $config->id,
            'flag_id' => $config->flag_id,
            'environment_key' => $config->environment->key,
            'state' => $config->state,
            'variation' => $this->valueOf($config->variation),
            'bucketing_attribute' => $config->bucketing_attribute,
            'bucketing_seed' => $config->bucketing_seed,
            'rules' => $config->rules->map(fn ($r) => [
                'id' => $r->id,
                'priority' => $r->priority,
                'description' => $r->description,
                'condition' => $r->condition,
                'variation' => $this->valueOf($r->variation),
            ])->all(),
            'updated_at' => $config->updated_at?->toIso8601String(),
        ];
    }

    private function valueOf(?array $wrapped): mixed
    {
        return $wrapped['v'] ?? null;
    }

    private function valueMatchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'bool' => is_bool($value),
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'json' => is_array($value) || is_object($value) || $value === null,
            default => false,
        };
    }
}
