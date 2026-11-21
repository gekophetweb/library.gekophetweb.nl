<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scan batch is a user's batch scan, or the automatic record of their single scans
 * with one scan setup, so admins can see and redo those too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_batches', function (Blueprint $table) {
            $table->string('mode')->default('batch')->after('ownership');
        });
    }

    public function down(): void
    {
        Schema::table('scan_batches', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
