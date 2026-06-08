<?php

declare(strict_types=1);

namespace Pennant\Laravel;

use Illuminate\Support\Facades\Facade;
use Pennant\Pennant as PennantSdk;

/**
 * @method static bool bool(string $key, bool $fallback)
 * @method static string string(string $key, string $fallback)
 * @method static float number(string $key, float $fallback)
 * @method static mixed json(string $key, mixed $fallback)
 * @method static array evaluate(string $key, ?array $contextOverride = null)
 * @method static void setContext(array $context)
 * @method static void refresh()
 */
class PennantFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PennantSdk::class;
    }
}
