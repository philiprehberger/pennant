<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\AuditEvent;
use App\Models\Workspace;
use Illuminate\Http\Request;

final class AuditLogger
{
    public static function record(
        Workspace $workspace,
        string $subjectType,
        string $subjectId,
        string $action,
        array $diff = [],
        ?string $reason = null,
        ?Request $request = null,
    ): AuditEvent {
        $actorType = 'system';
        $actorId = null;
        $actorLabel = 'system';

        if ($request !== null) {
            $key = $request->attributes->get('api_key');
            if ($key instanceof ApiKey) {
                $actorType = 'api_key';
                $actorId = $key->id;
                $actorLabel = $key->name ?: $key->prefix.'…'.$key->last_four;
            } elseif (auth()->check()) {
                $user = auth()->user();
                $actorType = 'user';
                $actorId = (string) $user->getKey();
                $actorLabel = (string) ($user->email ?? $user->name ?? 'user');
            }
        }

        return AuditEvent::create([
            'workspace_id' => $workspace->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_label' => $actorLabel,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'diff' => $diff,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
