<?php

namespace App\Jobs;

use App\Actions\Series\DetectSeries;
use App\Models\CollectionObject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Series detection for an item added outside a scan (a wishlist add from catalogue
 * search): external calls the user should not wait for.
 */
class DetectObjectSeries implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public CollectionObject $object, public string $language) {}

    public function handle(DetectSeries $detect): void
    {
        $detect->handle($this->object, $this->language);
    }
}
