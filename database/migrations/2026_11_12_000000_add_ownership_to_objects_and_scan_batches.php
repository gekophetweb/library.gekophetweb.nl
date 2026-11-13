<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rows land on `owned` through the default: no backfill needed.
        Schema::table('objects', function (Blueprint $table) {
            $table->string('ownership')->default('owned')->after('barcode');
            $table->index(['user_id', 'ownership']);
        });

        Schema::table('scan_batches', function (Blueprint $table) {
            $table->string('ownership')->default('owned')->after('language_id');
        });
    }

    public function down(): void
    {
        Schema::table('scan_batches', function (Blueprint $table) {
            $table->dropColumn('ownership');
        });

        Schema::table('objects', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'ownership']);
            $table->dropColumn('ownership');
        });
    }
};
