<?php

declare(strict_types=1);

namespace Pennant\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Pennant\Pennant;

class PennantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Pennant::class, function ($app) {
            return new Pennant([
                'api_base' => $app['config']['pennant.api_base'] ?? env('PENNANT_API_BASE', 'http://localhost:8000'),
                'api_key' => $app['config']['pennant.api_key'] ?? env('PENNANT_API_KEY', ''),
                'environment' => $app['config']['pennant.environment'] ?? env('PENNANT_ENV', 'prod'),
                'context' => $app['config']['pennant.context'] ?? [],
                'refresh_interval' => (int) ($app['config']['pennant.refresh_interval'] ?? 30),
            ]);
        });
    }

    public function boot(): void
    {
        // Blade directive: @pennant('flag-key') for booleans; uses a fluent
        // default of false. For string/json flags use Pennant::string(...)
        // inside an inline php block.
        Blade::if('pennant', function (string $key, bool $fallback = false) {
            /** @var Pennant $pennant */
            $pennant = app(Pennant::class);
            return $pennant->bool($key, $fallback);
        });
    }
}
