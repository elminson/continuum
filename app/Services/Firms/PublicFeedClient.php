<?php

namespace App\Services\Firms;

use App\Enums\FeedWindow;
use App\Enums\PublicFeed;
use App\Exceptions\FirmsException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads the key-free FIRMS active fire text feeds.
 */
class PublicFeedClient
{
    public function __construct(private readonly Http $http) {}

    public function url(PublicFeed $feed, FeedWindow $window): string
    {
        return rtrim((string) config('continuum.firms.base_url'), '/').'/'.$feed->path($window);
    }

    /**
     * Download the feed to local storage and return its absolute path.
     */
    public function download(PublicFeed $feed, FeedWindow $window): string
    {
        $disk = Storage::disk('local');
        $relative = sprintf('firms/feeds/%s_%s_%s.csv', $feed->value, $window->value, now()->format('Ymd_His'));
        $disk->makeDirectory('firms/feeds');
        $absolute = $disk->path($relative);

        $response = $this->http
            ->timeout((int) config('continuum.firms.timeout', 180))
            ->retry(3, 2000, throw: false)
            ->withOptions(['sink' => $absolute])
            ->get($this->url($feed, $window));

        if (! $response->successful()) {
            @unlink($absolute);

            throw FirmsException::unexpectedResponse('HTTP '.$response->status().' downloading '.$feed->value);
        }

        if (! is_file($absolute) || filesize($absolute) === 0) {
            file_put_contents($absolute, $response->body());
        }

        $head = (string) file_get_contents($absolute, length: 200);

        if (! str_starts_with(strtolower($head), 'latitude,longitude')) {
            @unlink($absolute);

            throw FirmsException::unexpectedResponse($head);
        }

        return $absolute;
    }
}
