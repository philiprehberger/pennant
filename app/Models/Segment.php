<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'key',
        'name',
        'description',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
