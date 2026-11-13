<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan share-with-account: a share for one person. Null = anyone with the link.
        // An e-mail, not a user id, so the owner can share before the person signs up and
        // cannot probe which addresses have an account.
        Schema::table('library_shares', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->after('label')->index();
        });
    }

    public function down(): void
    {
        Schema::table('library_shares', function (Blueprint $table) {
            $table->dropIndex(['recipient_email']);
            $table->dropColumn('recipient_email');
        });
    }
};
