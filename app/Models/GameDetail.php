<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Game-only data: the platform (§3.3). */
#[Fillable(['object_id', 'platform_id'])]
class GameDetail extends Model
{
    protected $primaryKey = 'object_id';

    public $incrementing = false;

    /** @return BelongsTo<Platform, $this> */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
