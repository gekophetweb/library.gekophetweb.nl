<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 64);
            $table->string('operation', 64);
            $table->text('query');
            $table->string('category', 32)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('outcome', 16);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_call_logs');
    }
};
