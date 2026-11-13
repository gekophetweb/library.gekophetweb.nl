<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_providers', function (Blueprint $table) {
            $table->id();
            $table->string('driver');
            $table->string('name');
            $table->string('client_id');
            $table->text('client_secret');
            $table->json('settings')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('allow_registration')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sso_provider_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['sso_provider_id', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');
        Schema::dropIfExists('sso_providers');
    }
};
