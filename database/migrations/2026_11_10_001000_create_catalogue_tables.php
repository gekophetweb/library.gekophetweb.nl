<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The core catalogue (guidelines §3): taxonomies as data, one shared `objects` table with
 * per-category detail tables, and per-user series with their known volumes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Top-level categories carry a `kind` (which detail table applies); sub-categories
        // inherit it, so new sub-categories need no code change.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique(); // ISO 639-1 where one exists
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_romanized');
            $table->string('name_english')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_romanized');
            $table->string('name_english')->nullable();
            $table->timestamps();
        });

        Schema::create('artist_language', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->primary(['artist_id', 'language_id']);
        });

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_romanized')->nullable();
            $table->string('name_english')->nullable();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'external_source', 'external_id']);
        });

        Schema::create('series_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->string('volume_number', 20); // as displayed: "3", "3.5"
            $table->decimal('volume_sort', 8, 2);
            // '' = the regular edition. Not nullable so the unique index treats it as a value.
            $table->string('edition')->default('');
            $table->string('title')->nullable();
            $table->json('isbns')->nullable();
            $table->string('release_date', 10)->nullable(); // "1990", "1990-12" or "1990-12-01"
            $table->timestamps();

            $table->unique(['series_id', 'volume_sort', 'edition']);
        });

        Schema::create('objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('name_romanized');
            $table->string('name_english')->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->date('release_date')->nullable();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'barcode']);
            $table->index(['user_id', 'category_id']);
        });

        Schema::create('object_language', function (Blueprint $table) {
            $table->foreignId('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->restrictOnDelete();
            $table->primary(['object_id', 'language_id']);
        });

        Schema::create('genre_object', function (Blueprint $table) {
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('object_id')->constrained('objects')->cascadeOnDelete();
            $table->primary(['genre_id', 'object_id']);
        });

        Schema::create('book_details', function (Blueprint $table) {
            $table->foreignId('object_id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('volume', 20)->nullable();
            $table->decimal('volume_sort', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('music_details', function (Blueprint $table) {
            $table->foreignId('object_id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('game_details', function (Blueprint $table) {
            $table->foreignId('object_id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'game_details', 'music_details', 'book_details', 'genre_object', 'object_language',
            'objects', 'series_volumes', 'series', 'platforms', 'artist_language', 'artists',
            'publishers', 'genres', 'languages', 'categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
