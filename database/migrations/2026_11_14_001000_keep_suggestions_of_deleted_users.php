<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deleted account's suggestions and comments stay (other people voted on and replied
     * to them); their author becomes empty and shows as "[deleted user]".
     */
    public function up(): void
    {
        foreach (['suggestions', 'suggestion_comments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['suggestions', 'suggestion_comments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
