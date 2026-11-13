<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan suggestion-votes: an admin puts a suggestion on the public board, where
        // every user can vote on it once.
        Schema::table('suggestions', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('admin_reply')->index();
        });

        Schema::create('suggestion_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['suggestion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_votes');

        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }
};
