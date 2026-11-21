<?php

namespace App\Models;

use App\Enums\CategoryKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['barcode', 'status', 'object_id', 'message'])]
class ScanBatchItem extends Model
{
    /** A single scan whose lookup is still running in the user's request: no job or redo touches it. */
    public const string LOOKING_UP = 'looking_up';

    /** @return BelongsTo<ScanBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScanBatch::class, 'scan_batch_id');
    }

    /** @return BelongsTo<CollectionObject, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(CollectionObject::class, 'object_id');
    }

    /**
     * The external API calls made while handling this code in a batch job (lookup and series
     * detection), newest first.
     *
     * @return HasMany<ApiCallLog, $this>
     */
    public function apiCalls(): HasMany
    {
        return $this->hasMany(ApiCallLog::class)->latest()->latest('id');
    }

    /**
     * A handled code can be redone when it did not reach the collection (looked up again),
     * or when it was found as a book without a series (series detection runs again).
     */
    public function isRedoable(): bool
    {
        return ! in_array($this->status, ['pending', self::LOOKING_UP], true) && ($this->object_id === null || $this->lacksSeries());
    }

    /** Found as a book that is in no series yet. */
    public function lacksSeries(): bool
    {
        return $this->object !== null
            && $this->object->serie_id === null
            && $this->object->category->topLevel()->kind === CategoryKind::Books;
    }
}
