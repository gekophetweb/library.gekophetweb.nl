<?php

namespace App\Models;

use Database\Factories\PlatformFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** PC or a console, for games (§3.3). */
#[Fillable(['name'])]
class Platform extends Model
{
    /** @use HasFactory<PlatformFactory> */
    use HasFactory;
}
