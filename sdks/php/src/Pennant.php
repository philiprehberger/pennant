<?php

declare(strict_types=1);

namespace Pennant;

/**
 * Pennant PHP SDK — server-side.
 *
 * ```php
 * $pennant = new Pennant\Pennant([
 *     'api_base' => 'https://api.pennant.philiprehberger.com',
 *     'api_key' => getenv('PENNANT_KEY'),
 *     'environment' => 'prod',
 *     'context' => ['userId' => 'alice', 'plan' => 'enterprise'],
 * ]);
 *
 * if ($pennant->bool('new-checkout-flow', false)) { ... }
 * ```
 *
 * The SDK fetches a snapshot on first read (or eagerly via `bootstrap()`),
 * caches it in memory, and evaluates rules locally. Configure
 * `refresh_interval` to poll the API at intervals; for real-time push use
 * the SSE endpoint via a separate process (the broadcaster).
 */
final class Pennant
{
    private array $config;
    private ?array $snapshot = null;
    /** @var array<string, array<string, mixed>> */
    private array $segments = [];
    private int $lastFetchTs = 0;

    /**
     * @param  array{api_base: string, api_key: string, environment: string, context?: array<string, mixed>, refresh_interval?: int, http_client?: callable, bootstrap?: array}  $config
     */
    public function __construct(array $config)
    {
        $config['context'] = $config['context'] ?? [];
        $config['refresh_interval'] = $config['refresh_interval'] ?? 30;
        $this->config = $config;

        if (isset($config['bootstrap'])) {
            $this->applySnapshot($config['bootstrap']);
        }
    }

    public function bool(string $key, bool $fallback): bool
    {
        $v = $this->evaluate($key)['value'];
        return is_bool($v) ? $v : $fallback;
    }

    public function string(string $key, string $fallback): string
    {
        $v = $this->evaluate($key)['value'];
        return is_string($v) ? $v : $fallback;
    }

    public function number(string $key, float $fallback): float
    {
        $v = $this->evaluate($key)['value'];
        return is_int($v) || is_float($v) ? (float) $v : $fallback;
    }

    /**
     * @template T
     * @param  T  $fallback
     * @return T|array<mixed>
     */
    public function json(string $key, mixed $fallback): mixed
    {
        $v = $this->evaluate($key)['value'];
        return is_array($v) ? $v : $fallback;
    }

    /**
     * @param  array<string, mixed>|null  $contextOverride
     * @return array{value: mixed, reason: string, rule_index: ?int}
     */
    public function evaluate(string $key, ?array $contextOverride = null): array
    {
        $this->maybeRefresh();

        $snap = $this->snapshot;
        if ($snap === null) {
            return ['value' => null, 'reason' => 'default', 'rule_index' => null];
        }

        $flag = null;
        foreach ($snap['flags'] as $f) {
            if (($f['key'] ?? null) === $key) {
                $flag = $f;
                break;
            }
        }
        if ($flag === null) {
            return ['value' => null, 'reason' => 'default', 'rule_index' => null];
        }

        if (($snap['kind'] ?? 'server') === 'client') {
            return [
                'value' => $flag['value'] ?? null,
                'reason' => $flag['reason'] ?? 'default',
                'rule_index' => null,
            ];
        }

        $resolver = function (string $segmentKey): ?array {
            $seg = $this->segments[$segmentKey] ?? null;
            return $seg === null ? null : ['condition' => $seg['condition']];
        };
        $evaluator = new FlagEvaluator(new RuleEvaluator($resolver));
        return $evaluator->evaluate($flag, $contextOverride ?? $this->config['context']);
    }

    public function setContext(array $context): void
    {
        $this->config['context'] = $context;
    }

    public function refresh(): void
    {
        $url = rtrim($this->config['api_base'], '/').'/v1/snapshot?environment='.urlencode($this->config['environment']);
        if (str_starts_with($this->config['api_key'], 'pn_clt_')) {
            $url .= '&context='.$this->base64url(json_encode($this->config['context']));
        }
        $body = $this->httpGet($url);
        if ($body === null) return;
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) return;
        $this->applySnapshot($decoded);
    }

    /** @param array<string, mixed> $snap */
    private function applySnapshot(array $snap): void
    {
        $this->snapshot = $snap;
        $this->segments = [];
        foreach ($snap['segments'] ?? [] as $seg) {
            if (isset($seg['key'])) {
                $this->segments[$seg['key']] = $seg;
            }
        }
        $this->lastFetchTs = time();
    }

    private function maybeRefresh(): void
    {
        if ($this->snapshot === null) {
            $this->refresh();
            return;
        }
        if ((time() - $this->lastFetchTs) > $this->config['refresh_interval']) {
            $this->refresh();
        }
    }

    private function httpGet(string $url): ?string
    {
        if (isset($this->config['http_client']) && is_callable($this->config['http_client'])) {
            return ($this->config['http_client'])($url, ['Authorization: Bearer '.$this->config['api_key']]);
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$this->config['api_key'], 'Accept: application/json'],
                CURLOPT_TIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($resp === false || $code >= 400) ? null : (string) $resp;
        }
        return @file_get_contents($url, false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer {$this->config['api_key']}\r\nAccept: application/json\r\n",
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ])) ?: null;
    }

    private function base64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
