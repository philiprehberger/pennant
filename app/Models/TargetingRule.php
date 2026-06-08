<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetingRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'flag_configuration_id',
        'priority',
        'description',
        'condition',
        'variation',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'condition' => 'array',
            'variation' => 'array',
        ];
    }

    public function flagConfiguration(): BelongsTo
    {
        return $this->belongsTo(FlagConfiguration::class);
    }
}
