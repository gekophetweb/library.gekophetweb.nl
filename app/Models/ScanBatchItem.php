<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
