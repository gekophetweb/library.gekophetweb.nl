<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** No longer in the default list (CatalogueSeeder); removed where nothing uses them. */
    private const array CODES = ['zh', 'fr', 'de'];

    public function up(): void
    {
        // A language that is in use (an object, an artist or a scan batch) stays: deleting
        // it would cascade to that data.
        DB::table('languages')
            ->whereIn('code', self::CODES)
            ->whereNotExists(fn ($query) => $query->from('object_language')->whereColumn('object_language.language_id', 'languages.id'))
            ->whereNotExists(fn ($query) => $query->from('artist_language')->whereColumn('artist_language.language_id', 'languages.id'))
            ->whereNotExists(fn ($query) => $query->from('scan_batches')->whereColumn('scan_batches.language_id', 'languages.id'))
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        foreach (['de' => 'German', 'fr' => 'French', 'zh' => 'Chinese'] as $code => $name) {
            DB::table('languages')->insertOrIgnore(['code' => $code, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
};
