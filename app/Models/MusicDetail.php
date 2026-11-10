<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Music-only data: the artist (§3.3). */
#[Fillable(['object_id', 'artist_id'])]
class MusicDetail extends Model
{
    protected $primaryKey = 'object_id';

    public $incrementing = false;

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
