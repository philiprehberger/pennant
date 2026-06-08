<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUlids;

    public const KIND_SERVER = 'server';
    public const KIND_CLIENT = 'client';

    protected $fillable = [
        'workspace_id',
        'environment_id',
        'name',
        'kind',
        'prefix',
        'key_hash',
        'last_four',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Mint a key and return [model, plaintext].
     * The plaintext must be captured at creation time — it is never retrievable again.
     *
     * Format: pn_{srv|clt}_{live|test}_{32 random chars}
     */
    public static function mint(
        Workspace $workspace,
        string $kind,
        string $env = 'live',
        ?Environment $environment = null,
        ?string $name = null,
    ): array {
        if (! in_array($kind, [self::KIND_SERVER, self::KIND_CLIENT], true)) {
            throw new \InvalidArgumentException("kind must be 'server' or 'client'");
        }
        if (! in_array($env, ['live', 'test'], true)) {
            throw new \InvalidArgumentException("env must be 'live' or 'test'");
        }

        $kindCode = $kind === self::KIND_SERVER ? 'srv' : 'clt';
        $prefix = "pn_{$kindCode}_{$env}_";
        $random = Str::random(32);
        $plaintext = $prefix.$random;

        $apiKey = static::create([
            'workspace_id' => $workspace->id,
            'environment_id' => $environment?->id,
            'name' => $name,
            'kind' => $kind,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $plaintext),
            'last_four' => substr($plaintext, -4),
        ]);

        return [$apiKey, $plaintext];
    }

    public static function findByPlaintext(?string $plaintext): ?self
    {
        if (! is_string($plaintext) || $plaintext === '') {
            return null;
        }

        return static::query()
            ->whereNull('revoked_at')
            ->where('key_hash', hash('sha256', $plaintext))
            ->first();
    }

    public function isServer(): bool
    {
        return $this->kind === self::KIND_SERVER;
    }

    public function isClient(): bool
    {
        return $this->kind === self::KIND_CLIENT;
    }
}
