<?php

namespace App\Jobs;

use App\Actions\Ingestion\ImportFirmsFile;
use App\Enums\FeedWindow;
use App\Enums\PublicFeed;
use App\Services\Firms\PublicFeedClient;
use App\Support\Geo\BoundingBox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Backoff(120)]
#[Timeout(1800)]
class IngestPublicFeedJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    public function __construct(
        public PublicFeed $feed,
        public FeedWindow $window = FeedWindow::Day,
        public ?array $bbox = null,
    ) {}

    public function handle(PublicFeedClient $client, ImportFirmsFile $import): void
    {
        $path = $client->download($this->feed, $this->window);

        try {
            $import(
                $path,
                sprintf('public:%s:%s', $this->feed->value, $this->window->value),
                $this->bbox === null ? null : BoundingBox::fromArray($this->bbox),
            );
        } finally {
            @unlink($path);
        }
    }
}
