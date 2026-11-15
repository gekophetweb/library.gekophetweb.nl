<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps what an external API answered, and which scanned code a batch call was made for,
 * so an admin can see the raw data behind a scan batch outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_call_logs', function (Blueprint $table) {
            $table->foreignId('scan_batch_item_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->mediumText('response_body')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('api_call_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scan_batch_item_id');
            $table->dropColumn('response_body');
        });
    }
};
