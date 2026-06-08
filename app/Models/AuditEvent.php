<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'actor_type',
        'actor_id',
        'actor_label',
        'subject_type',
        'subject_id',
        'action',
        'diff',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'diff' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
