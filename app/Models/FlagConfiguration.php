<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FlagConfiguration extends Model
{
    use HasUlids;

    public const STATE_ON = 'on';
    public const STATE_OFF = 'off';

    protected $fillable = [
        'flag_id',
        'environment_id',
        'state',
        'variation',
        'bucketing_attribute',
        'bucketing_seed',
    ];

    protected function casts(): array
    {
        return [
            'variation' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $config) {
            if (empty($config->bucketing_seed)) {
                $config->bucketing_seed = (string) Str::ulid();
            }
        });
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TargetingRule::class)->orderBy('priority');
    }
}
