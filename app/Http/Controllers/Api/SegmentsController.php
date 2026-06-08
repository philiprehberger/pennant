<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\SegmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SegmentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $segments = $workspace->segments()->orderBy('created_at')->get();

        return response()->json(['data' => $segments->map(fn ($s) => $this->serialize($s))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        $data = Validator::make($request->all(), [
            'key' => ['required', 'string', 'min:1', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'condition' => ['required', 'array'],
        ])->validate();

        if ($workspace->segments()->where('key', $data['key'])->exists()) {
            return $this->problem(409, "A segment with key '{$data['key']}' already exists.");
        }

        $cycle = (new SegmentResolver($workspace))->detectCycle($data['key'], $data['condition']);
        if ($cycle !== []) {
            return $this->problem(400, 'Segment references form a cycle: '.implode(' → ', $cycle));
        }

        $segment = $workspace->segments()->create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'condition' => $data['condition'],
        ]);

        AuditLogger::record($workspace, 'segment', $segment->id, 'created', ['after' => $segment->only(['key', 'name', 'condition'])], request: $request);

        return response()->json($this->serialize($segment), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->serialize($this->find($request, $id)));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $segment = $this->find($request, $id);
        $workspace = $segment->workspace;

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'condition' => ['sometimes', 'array'],
        ])->validate();

        if (isset($data['condition'])) {
            $cycle = (new SegmentResolver($workspace))->detectCycle($segment->key, $data['condition']);
            if ($cycle !== []) {
                return $this->problem(400, 'Segment references form a cycle: '.implode(' → ', $cycle));
            }
        }

        $before = $segment->only(['name', 'description', 'condition']);
        $segment->fill($data)->save();
        AuditLogger::record($workspace, 'segment', $segment->id, 'updated', ['before' => $before, 'after' => $segment->only(['name', 'description', 'condition'])], request: $request);

        return response()->json($this->serialize($segment));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $segment = $this->find($request, $id);
        $workspace = $segment->workspace;
        $segment->delete();

        AuditLogger::record($workspace, 'segment', $id, 'deleted', request: $request);

        return response()->json(status: 204);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }

    private function find(Request $request, string $id): Segment
    {
        return $this->workspace($request)->segments()->findOrFail($id);
    }

    private function serialize(Segment $segment): array
    {
        return [
            'id' => $segment->id,
            'key' => $segment->key,
            'name' => $segment->name,
            'description' => $segment->description,
            'condition' => $segment->condition,
            'created_at' => $segment->created_at?->toIso8601String(),
        ];
    }

    private function problem(int $status, string $detail): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => $status === 409 ? 'Conflict' : 'Invalid request',
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
