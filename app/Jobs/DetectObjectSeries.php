<?php

namespace App\Jobs;

use App\Actions\Series\DetectSeries;
use App\Models\CollectionObject;
use App\Services\ApiCallLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Context;

/**
 * Series detection for an item added outside a scan (a wishlist add from catalogue
 * search), or redone for a scanned code: external calls the user should not wait for.
 * For a scanned code, the calls are logged against it (raw answers for admins).
 */
class DetectObjectSeries implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public CollectionObject $object, public string $language, public ?int $scanBatchItemId = null) {}

    public function handle(DetectSeries $detect): void
    {
        if ($this->scanBatchItemId === null) {
            $detect->handle($this->object, $this->language);

            return;
        }

        Context::scope(
            fn () => $detect->handle($this->object, $this->language),
            hidden: [ApiCallLogger::SCAN_BATCH_ITEM => $this->scanBatchItemId],
        );
    }
}
