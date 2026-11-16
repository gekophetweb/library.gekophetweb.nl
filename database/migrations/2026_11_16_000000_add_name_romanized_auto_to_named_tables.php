<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether `name_romanized` was filled in automatically (romanized from a kana / Hangul
     * reading, or a Wikidata label), so a later redo may replace it. A hand-typed one never
     * is. Existing rows count as not automatic; a copy of the original name is a
     * placeholder either way.
     */
    public function up(): void
    {
        foreach (['objects', 'publishers', 'artists'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('name_romanized_auto')->default(false)->after('name_romanized');
            });
        }
    }

    public function down(): void
    {
        foreach (['objects', 'publishers', 'artists'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('name_romanized_auto');
            });
        }
    }
};
