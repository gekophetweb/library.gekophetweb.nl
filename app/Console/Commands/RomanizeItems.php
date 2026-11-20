<?php

namespace App\Console\Commands;

use App\Jobs\RomanizeObjects;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bulk redo of the romanized names of existing items, like the admin overview's button:
 * every Japanese or Korean item whose title, publisher or author still has a placeholder.
 */
#[Signature('romanize:items')]
#[Description('Queue a romanization redo for every Japanese or Korean item that still needs one')]
class RomanizeItems extends Command
{
    public function handle(): int
    {
        $count = RomanizeObjects::dispatchAllNeeded();

        $this->info("Queued {$count} ".str('item')->plural($count).'.');

        return self::SUCCESS;
    }
}
