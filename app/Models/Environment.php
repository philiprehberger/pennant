<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Environment extends Model
{
    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'key',
        'name',
        'production',
        'require_approval',
    ];

    protected function casts(): array
    {
        return [
            'production' => 'boolean',
            'require_approval' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function flagConfigurations(): HasMany
    {
        return $this->hasMany(FlagConfiguration::class);
    }
}
