<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A second credential for providers that need one (Rakuten: the access key next to the application ID). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_api_keys', function (Blueprint $table) {
            $table->text('secret')->nullable()->after('key'); // encrypted cast
        });
    }

    public function down(): void
    {
        Schema::table('user_api_keys', function (Blueprint $table) {
            $table->dropColumn('secret');
        });
    }
};
