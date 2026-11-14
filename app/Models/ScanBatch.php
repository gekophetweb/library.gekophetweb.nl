<?php

namespace App\Models;

use App\Enums\OwnershipStatus;
use App\Jobs\ProcessScanBatch;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Scan\ScanSetup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A batch scan. Its items are a working list until the batch is committed. */
#[Fillable(['category_id', 'sub_category_id', 'language_id', 'ownership', 'status', 'committed_at', 'finished_at'])]
class ScanBatch extends Model
{
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
        $count = $this->items()->where('status', 'failed')->update(['status' => 'pending', 'message' => null]);

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
