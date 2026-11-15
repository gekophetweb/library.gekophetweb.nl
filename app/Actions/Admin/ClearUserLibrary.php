<?php

namespace App\Actions\Admin;

use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Empties a user's library for an admin: every scan batch with its codes, every object
 * (collection and wishlist) and the user's series. Foreign keys take the objects'
 * details, languages and genres and the series' volumes along; API call logs stay, no
 * longer linked to a code. Share links to one of the series go too, as they would point
 * at nothing; whole-library and category links stay. The account itself is left alone.
 */
class ClearUserLibrary
{
    /** @return array{objects: int, batches: int, series: int} how many of each were removed */
    public function handle(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $seriesIds = $user->series()->pluck('id');

            LibraryShare::query()
                ->where('shareable_type', (new Series)->getMorphClass())
                ->whereIn('shareable_id', $seriesIds)
                ->delete();

            return [
                'batches' => $user->scanBatches()->delete(),
                'objects' => $user->objects()->delete(),
                'series' => $user->series()->delete(),
            ];
        });
    }
}
