<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Batch scans (guidelines §4.2): a working list that is only added to the account on commit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft → queued → processing → done
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scan_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_batch_id')->constrained()->cascadeOnDelete();
            $table->string('barcode');
            $table->string('status')->default('pending'); // pending, added, already_owned, not_found, invalid, failed
            $table->foreignId('object_id')->nullable()->constrained('objects')->nullOnDelete();
            $table->string('message')->nullable();
            $table->timestamps();

            $table->unique(['scan_batch_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_batch_items');
        Schema::dropIfExists('scan_batches');
    }
};
