<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        $limit = max(1, min(100, (int) $request->query('limit', 25)));
        $cursor = $request->query('cursor');

        $query = $workspace->auditEvents()->orderByDesc('id');
        if ($cursor) {
            $query->where('id', '<', $cursor);
        }
        if ($flagKey = $request->query('flag')) {
            $flag = $workspace->flags()->where('key', $flagKey)->first();
            if ($flag) {
                $query->where(function ($q) use ($flag) {
                    $q->where(function ($qq) use ($flag) {
                        $qq->where('subject_type', 'flag')->where('subject_id', $flag->id);
                    })->orWhere(function ($qq) use ($flag) {
                        $qq->where('subject_type', 'flag_configuration')->where('diff->flag_id', $flag->id);
                    });
                });
            } else {
                $query->whereRaw('1=0');
            }
        }

        $events = $query->limit($limit + 1)->get();
        $hasMore = $events->count() > $limit;
        $page = $events->take($limit);

        return response()->json([
            'data' => $page->map(fn ($e) => [
                'id' => $e->id,
                'actor_type' => $e->actor_type,
                'actor_id' => $e->actor_id,
                'actor_label' => $e->actor_label,
                'subject_type' => $e->subject_type,
                'subject_id' => $e->subject_id,
                'action' => $e->action,
                'diff' => $e->diff,
                'reason' => $e->reason,
                'created_at' => $e->created_at?->toIso8601String(),
            ])->all(),
            'next_cursor' => $hasMore ? $page->last()->id : null,
        ]);
    }
}
