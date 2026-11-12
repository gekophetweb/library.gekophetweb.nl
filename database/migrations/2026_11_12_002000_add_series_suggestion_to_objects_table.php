<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A series match that was not sure enough to apply automatically (plan
        // series-detection-from-isbn §5 step 14): shown on the item page to accept or dismiss.
        Schema::table('objects', function (Blueprint $table) {
            $table->json('series_suggestion')->nullable()->after('serie_id');
        });
    }

    public function down(): void
    {
        Schema::table('objects', function (Blueprint $table) {
            $table->dropColumn('series_suggestion');
        });
    }
};
