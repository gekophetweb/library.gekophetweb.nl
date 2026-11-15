<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['barcode', 'status', 'object_id', 'message'])]
class ScanBatchItem extends Model
{
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

    /** An admin can redo a code that was handled but did not reach the collection. */
    public function isRedoable(): bool
    {
        return $this->object_id === null && $this->status !== 'pending';
    }
}
