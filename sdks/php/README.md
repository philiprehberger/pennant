# philiprehberger/pennant — PHP SDK

PHP client for [Pennant](https://github.com/philiprehberger/pennant) — feature flag evaluation with deterministic local bucketing and snapshot bootstrap, with a first-class Laravel integration.

```bash
composer require philiprehberger/pennant
```

## Quickstart

```php
use Pennant\Pennant;

$pennant = new Pennant([
    'api_base' => 'https://api.pennant.philiprehberger.com',
    'api_key' => getenv('PENNANT_KEY'),
    'environment' => 'prod',
    'context' => ['userId' => 'alice', 'plan' => 'enterprise'],
]);

if ($pennant->bool('new-checkout-flow', false)) {
    // ...
}

$cta = $pennant->string('hero-cta-copy', 'Get started');
$limits = $pennant->json('plan-limits', ['uploads' => 10]);
```

## Laravel integration

The package auto-registers `Pennant\Laravel\PennantServiceProvider` and the `Pennant` facade.

Configure via env or `config/pennant.php`:

```env
PENNANT_API_BASE=https://api.pennant.philiprehberger.com
PENNANT_API_KEY=pn_srv_live_...
PENNANT_ENV=prod
```

Read a flag in code via the facade:

```php
use Pennant\Laravel\PennantFacade as Pennant;

if (Pennant::bool('new-checkout-flow', false)) {
    // ...
}
```

Or in a Blade template:

```blade
@pennant('new-checkout-flow')
    <NewCheckout />
@else
    <LegacyCheckout />
@endpennant
```

## Drift corpus

This SDK ships a `tests/CorpusTest.php` that runs the cross-implementation rule-engine corpus at the repo root (`tests/corpus/rules.json`). The same corpus runs against the Laravel server (PHPUnit) and the TypeScript SDK (Vitest). Any divergence between implementations fails CI on all of them.

## License

MIT
