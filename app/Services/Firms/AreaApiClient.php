<?php

namespace App\Services\Firms;

use App\Enums\FirmsSource;
use App\Exceptions\FirmsException;
use App\Support\Geo\BoundingBox;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Client for the FIRMS Area API (requires a free MAP_KEY).
 *
 * @see https://firms.modaps.eosdis.nasa.gov/api/area/
 */
class AreaApiClient
{
    public const int MAX_DAYS = 5;

    public const string LIMITER_KEY = 'firms-area-api';

    public const string PAUSE_KEY = 'firms:paused-until';

    public const string STATUS_KEY = 'firms:key-status';

    public const int STATUS_TTL_SECONDS = 20;

    public const int SAFETY_MARGIN = 150;

    public const int PROBE_PAUSE_SECONDS = 60;

    public const int EXCEEDED_PAUSE_SECONDS = 120;

    public function __construct(
        private readonly Http $http,
        private readonly RateLimiter $limiter,
        private readonly Cache $cache,
    ) {}

    public function hasKey(): bool
    {
        return filled(config('continuum.firms.map_key'));
    }

    /**
     * Fetch up to five days of detections for a bounding box as CSV text.
     */
    public function fetchCsv(FirmsSource $source, BoundingBox $box, int $days = self::MAX_DAYS, ?string $date = null): string
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $url = $this->url($source, $box, $days, $date);

        $weight = max(1, $days * (int) config('continuum.firms.rate_limit.transaction_weight', 2));
        $budget = (int) config('continuum.firms.rate_limit.transactions', 4500);
        $decay = (int) config('continuum.firms.rate_limit.decay_seconds', 600);

        if ($this->pausedFor() > 0) {
            throw FirmsException::rateLimited();
        }

        if ($this->limiter->attempts(self::LIMITER_KEY) + $weight > $budget) {
            throw FirmsException::rateLimited();
        }

        if ($this->firmsBudgetNearlyUsed($weight)) {
            $this->pause(self::PROBE_PAUSE_SECONDS);

            throw FirmsException::rateLimited();
        }

        $this->limiter->increment(self::LIMITER_KEY, $decay, $weight);

        return $this->request($url);
    }

    /**
     * Pause every FIRMS request for a number of seconds (shared across workers).
     */
    public function pause(int $seconds): void
    {
        $until = now()->addSeconds($seconds)->getTimestamp();

        if ($until > (int) $this->cache->get(self::PAUSE_KEY, 0)) {
            $this->cache->put(self::PAUSE_KEY, $until, $seconds + 5);
        }
    }

    /**
     * Seconds left on the current pause, or zero.
     */
    public function pausedFor(): int
    {
        return max(0, (int) $this->cache->get(self::PAUSE_KEY, 0) - now()->getTimestamp());
    }

    /**
     * Seconds until the transaction budget resets.
     */
    public function budgetResetsIn(): int
    {
        return max($this->pausedFor(), $this->limiter->availableIn(self::LIMITER_KEY), 30);
    }

    /**
     * Ask FIRMS how much of the 10-minute budget is spent (cached briefly so
     * the status endpoint is not hammered) and stop just short of the limit.
     */
    private function firmsBudgetNearlyUsed(int $weight): bool
    {
        if (! $this->hasKey()) {
            return false;
        }

        $status = $this->cache->remember(self::STATUS_KEY, self::STATUS_TTL_SECONDS, fn () => $this->keyStatus() ?? []);

        if (! isset($status['current_transactions'], $status['transaction_limit'])) {
            return false;
        }

        return (int) $status['current_transactions'] + $weight > (int) $status['transaction_limit'] - self::SAFETY_MARGIN;
    }

    public function url(FirmsSource $source, BoundingBox $box, int $days, ?string $date = null): string
    {
        $key = config('continuum.firms.map_key');

        if (blank($key)) {
            throw FirmsException::missingMapKey();
        }

        $url = sprintf(
            '%s/api/area/csv/%s/%s/%s/%d',
            rtrim((string) config('continuum.firms.base_url'), '/'),
            $key,
            $source->value,
            $box->toFirmsString(),
            $days,
        );

        return $date === null ? $url : $url.'/'.$date;
    }

    /**
     * Remaining transactions in the current 10-minute window, if FIRMS reports it.
     *
     * @return array<string, mixed>|null
     */
    public function keyStatus(): ?array
    {
        if (! $this->hasKey()) {
            return null;
        }

        $response = $this->http->timeout(30)->get(sprintf(
            '%s/mapserver/mapkey_status/?MAP_KEY=%s',
            rtrim((string) config('continuum.firms.base_url'), '/'),
            config('continuum.firms.map_key'),
        ));

        return $response->successful() ? $response->json() : null;
    }

    private function budgetExhausted(): bool
    {
        $status = $this->keyStatus();

        if ($status === null || ! isset($status['current_transactions'], $status['transaction_limit'])) {
            return false;
        }

        return (int) $status['current_transactions'] >= (int) $status['transaction_limit'];
    }

    private function request(string $url): string
    {
        $response = $this->http
            ->timeout((int) config('continuum.firms.timeout', 180))
            ->retry(3, 3000, fn (Throwable $exception) => $exception instanceof ConnectionException, throw: false)
            ->get($url);

        $body = $response->body();

        $isCsv = str_starts_with(strtolower(ltrim($body)), 'latitude,longitude');

        // "Exceeding allowed transaction limit": pause everyone until the window moves on.
        if (! $isCsv && preg_match('/exceed|transaction limit|too many/i', $body) === 1) {
            $this->markBudgetExhausted();

            throw FirmsException::rateLimited();
        }

        // FIRMS answers "HTTP 400 Invalid MAP_KEY" both for a bad key and for a
        // key whose 10-minute transaction budget is spent; ask which it is.
        if (! $isCsv && str_contains($body, 'Invalid MAP_KEY')) {
            if ($this->budgetExhausted()) {
                $this->markBudgetExhausted();

                throw FirmsException::rateLimited();
            }

            throw FirmsException::invalidMapKey();
        }

        if (! $response->successful()) {
            throw FirmsException::unexpectedResponse('HTTP '.$response->status().' '.$body);
        }

        if (! $isCsv) {
            throw FirmsException::unexpectedResponse($body);
        }

        return $body;
    }

    /**
     * FIRMS said no: pause and treat the local budget as spent so that no
     * worker retries until the 10-minute window has moved on.
     */
    private function markBudgetExhausted(): void
    {
        $this->pause(self::EXCEEDED_PAUSE_SECONDS);
        $this->cache->forget(self::STATUS_KEY);

        $budget = (int) config('continuum.firms.rate_limit.transactions', 4500);
        $missing = $budget - $this->limiter->attempts(self::LIMITER_KEY);

        if ($missing > 0) {
            $this->limiter->increment(self::LIMITER_KEY, (int) config('continuum.firms.rate_limit.decay_seconds', 600), $missing);
        }
    }
}
