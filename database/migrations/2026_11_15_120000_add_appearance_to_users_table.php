<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One JSON column for each user's own look (plan redesign-themes §3). Null means the
     * standard look; App\Support\Appearance\Appearance fills in every missing option.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('appearance')->nullable()->after('suggestion_emails');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
