<?php

namespace App\Models;

use App\Enums\OwnershipStatus;
use App\Jobs\DetectObjectSeries;
use App\Jobs\ProcessScanBatch;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Scan\ScanSetup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch scan (its items are a working list until the batch is committed), or the
 * automatic record of a user's single scans with one scan setup (mode `single`).
 */
#[Fillable(['category_id', 'sub_category_id', 'language_id', 'ownership', 'mode', 'status', 'committed_at', 'finished_at'])]
class ScanBatch extends Model
{
    public const string MODE_BATCH = 'batch';

    public const string MODE_SINGLE = 'single';

    /** @var array<string, mixed> */
    protected $attributes = ['mode' => self::MODE_BATCH];

    public const string DRAFT = 'draft';

    public const string QUEUED = 'queued';

    public const string PROCESSING = 'processing';

    public const string DONE = 'done';

    protected function casts(): array
    {
        return ['committed_at' => 'datetime', 'finished_at' => 'datetime', 'ownership' => OwnershipStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return HasMany<ScanBatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ScanBatchItem::class)->orderBy('id');
    }

    /** The automatic record of single scans, not a batch the user made. */
    public function isSingle(): bool
    {
        return $this->mode === self::MODE_SINGLE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /** Committed and still being worked through by the queued job. */
    public function isRunning(): bool
    {
        return in_array($this->status, [self::QUEUED, self::PROCESSING], true);
    }

    /** A finished batch with items whose lookup failed (no catalogue answered). */
    public function canRetry(): bool
    {
        return $this->status === self::DONE && $this->items()->where('status', 'failed')->exists();
    }

    /**
     * Puts the failed items back to pending and queues the batch again; the job only looks
     * at pending items, so the ones already added are left alone. Returns how many.
     */
    public function retryFailed(): int
    {
        return $this->requeue($this->items()->where('status', 'failed'));
    }

    /** Outcomes an admin's redo looks up again: everything that did not reach the collection (for lack of an answer, or a single scan the user didn't add). */
    public const array REDOABLE = ['failed', 'not_found', 'not_added'];

    /**
     * An admin's redo of a finished batch: looks every code that was not added up again, for
     * example after a provider or key was fixed. Added and already-owned codes are left alone,
     * and so are invalid barcodes. Uses the batch owner's keys, like the first run.
     */
    public function redo(): int
    {
        return $this->requeue($this->items()->whereIn('status', self::REDOABLE));
    }

    /**
     * A redo of picked codes (an admin's ticks, or the owner's one at a time): any of them
     * that did not reach the collection is looked up again, and any found book without a
     * series gets its series detected again. Other found codes are left alone. Returns how many.
     *
     * @param  list<int>  $itemIds
     */
    public function redoItems(array $itemIds): int
    {
        return $this->redetectSeries($itemIds)
            + $this->requeue($this->items()->whereKey($itemIds)->whereNotIn('status', ['pending', ScanBatchItem::LOOKING_UP]));
    }

    /**
     * Series detection again for the picked codes found as a book without a series, for
     * example because the series APIs did not answer the first time. Queued, like the first
     * detection; its calls are logged against the code.
     *
     * @param  list<int>  $itemIds
     */
    private function redetectSeries(array $itemIds): int
    {
        return $this->items()->whereKey($itemIds)->whereNotNull('object_id')->with('object.category')->get()
            ->filter->lacksSeries()
            ->each(fn (ScanBatchItem $item) => DetectObjectSeries::dispatch($item->object, $this->language->code, $item->id))
            ->count();
    }

    /** @param  HasMany<ScanBatchItem, $this>  $items */
    private function requeue(HasMany $items): int
    {
        $count = $items->whereNull('object_id')->update(['status' => 'pending', 'message' => null]);

        if ($count > 0) {
            $this->update(['status' => self::QUEUED, 'finished_at' => null]);
            ProcessScanBatch::dispatch($this);
        }

        return $count;
    }

    public function setup(): ScanSetup
    {
        return new ScanSetup($this->category, $this->subCategory, $this->language, $this->ownership ?? OwnershipStatus::Owned);
    }

    public function context(): CatalogueContext
    {
        return new CatalogueContext($this->user, $this->category, $this->language->code);
    }
}
