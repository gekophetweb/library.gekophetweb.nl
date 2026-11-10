<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Book-only data: the author and the volume / book number (`3.5` for a side story). */
#[Fillable(['object_id', 'artist_id', 'volume', 'volume_sort'])]
class BookDetail extends Model
{
    protected $primaryKey = 'object_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['volume_sort' => 'decimal:2'];
    }

    /** @return BelongsTo<CollectionObject, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(CollectionObject::class, 'object_id');
    }

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
