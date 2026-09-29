<?php

namespace App\Console\Commands;

use App\Actions\Ingestion\ImportFirmsFile;
use App\Enums\FeedWindow;
use App\Enums\PublicFeed;
use App\Jobs\IngestPublicFeedJob;
use App\Models\Region;
use App\Services\Firms\PublicFeedClient;
use App\Support\Geo\BoundingBox;
use Illuminate\Console\Command;
use Throwable;

class IngestPublicFeedCommand extends Command
{
    protected $signature = 'firms:ingest-public
        {--feed=* : Feeds to ingest: modis, viirs-snpp, viirs-noaa20, viirs-noaa21 (default: all)}
        {--window=24h : 24h, 48h or 7d}
        {--bbox= : Keep only detections inside west,south,east,north}
        {--presets : Keep only detections inside the preset regions (union of their boxes)}
        {--queue : Dispatch queued jobs instead of running now}';

    protected $description = 'Download the key-free FIRMS global feeds and import them';

    public function handle(PublicFeedClient $client, ImportFirmsFile $import): int
    {
        $window = FeedWindow::tryFrom((string) $this->option('window'));

        if ($window === null) {
            $this->error('Window must be 24h, 48h or 7d.');

            return self::INVALID;
        }

        $feeds = $this->option('feed') === []
            ? PublicFeed::cases()
            : array_map(fn ($feed) => PublicFeed::from((string) $feed), (array) $this->option('feed'));

        $clip = $this->clip();

        foreach ($feeds as $feed) {
            if ($this->option('queue')) {
                IngestPublicFeedJob::dispatch($feed, $window, $clip?->toArray());
                $this->components->info("Queued {$feed->label()} ({$window->value})");

                continue;
            }

            $this->components->task("{$feed->label()} {$window->value}", function () use ($client, $import, $feed, $window, $clip): void {
                $path = $client->download($feed, $window);

                try {
                    $summary = $import($path, "public:{$feed->value}:{$window->value}", $clip);
                    $this->newLine();
                    $this->components->twoColumnDetail('  rows read / inserted / skipped', number_format($summary->read).' / '.number_format($summary->inserted).' / '.number_format($summary->skipped));
                } finally {
                    @unlink($path);
                }
            });
        }

        return self::SUCCESS;
    }

    private function clip(): ?BoundingBox
    {
        if ($this->option('bbox')) {
            return BoundingBox::fromString((string) $this->option('bbox'));
        }

        if (! $this->option('presets')) {
            return null;
        }

        try {
            $regions = Region::query()->preset()->get();
        } catch (Throwable) {
            return null;
        }

        if ($regions->isEmpty()) {
            return null;
        }

        return new BoundingBox(
            $regions->min('west'),
            $regions->min('south'),
            $regions->max('east'),
            $regions->max('north'),
        );
    }
}
