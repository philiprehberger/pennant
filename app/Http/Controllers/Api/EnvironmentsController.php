<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnvironmentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        $envs = $workspace->environments()->orderBy('created_at')->get();

        return response()->json(['data' => $envs->map(fn ($e) => $this->serialize($e))]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        $data = Validator::make($request->all(), [
            'key' => ['required', 'string', 'min:1', 'max:32', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'production' => ['boolean'],
            'require_approval' => ['boolean'],
        ])->validate();

        if ($workspace->environments()->where('key', $data['key'])->exists()) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Conflict',
                'status' => 409,
                'detail' => "An environment with key '{$data['key']}' already exists in this workspace.",
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        $env = $workspace->environments()->create([
            'key' => $data['key'],
            'name' => $data['name'],
            'production' => (bool) ($data['production'] ?? false),
            'require_approval' => (bool) ($data['require_approval'] ?? false),
        ]);

        return response()->json($this->serialize($env), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $env = $this->find($request, $id);

        return response()->json($this->serialize($env));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $env = $this->find($request, $id);

        $data = Validator::make($request->all(), [
            'key' => ['sometimes', 'string', 'min:1', 'max:32', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'production' => ['sometimes', 'boolean'],
            'require_approval' => ['sometimes', 'boolean'],
        ])->validate();

        $env->fill($data)->save();

        return response()->json($this->serialize($env));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $env = $this->find($request, $id);
        $env->delete();

        return response()->json(status: 204);
    }

    private function workspace(Request $request): Workspace
    {
        /** @var Workspace $w */
        $w = $request->attributes->get('workspace');

        return $w;
    }

    private function find(Request $request, string $id): Environment
    {
        return $this->workspace($request)->environments()->findOrFail($id);
    }

    private function serialize(Environment $env): array
    {
        return [
            'id' => $env->id,
            'key' => $env->key,
            'name' => $env->name,
            'production' => $env->production,
            'require_approval' => $env->require_approval,
            'created_at' => $env->created_at?->toIso8601String(),
        ];
    }
}
