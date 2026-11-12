<?php

namespace App\Models;

use App\Support\Catalogue\CatalogueContext;
use App\Support\Scan\ScanSetup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A batch scan. Its items are a working list until the batch is committed. */
#[Fillable(['category_id', 'sub_category_id', 'language_id', 'status', 'committed_at', 'finished_at'])]
class ScanBatch extends Model
{
    public const string DRAFT = 'draft';

    public const string QUEUED = 'queued';

    public const string PROCESSING = 'processing';

    public const string DONE = 'done';

    protected function casts(): array
    {
        return ['committed_at' => 'datetime', 'finished_at' => 'datetime'];
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

    public function setup(): ScanSetup
    {
        return new ScanSetup($this->category, $this->subCategory, $this->language);
    }

    public function context(): CatalogueContext
    {
        return new CatalogueContext($this->user, $this->category, $this->language->code);
    }
}
