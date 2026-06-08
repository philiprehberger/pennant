<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flag extends Model
{
    use HasUlids;

    public const TYPE_BOOL = 'bool';
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_JSON = 'json';

    protected $fillable = [
        'workspace_id',
        'key',
        'type',
        'description',
        'default_value',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(FlagConfiguration::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
