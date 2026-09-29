<?php

namespace App\Jobs;

use App\Actions\Harmonization\RefreshRegion;
use App\Models\Region;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(2)]
#[Timeout(3600)]
class HarmonizeRegionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public int $regionId, public bool $aggregate = true) {}

    public function uniqueId(): string
    {
        return (string) $this->regionId;
    }

    public function handle(RefreshRegion $refresh): void
    {
        $refresh(Region::query()->findOrFail($this->regionId), $this->aggregate);
    }
}
