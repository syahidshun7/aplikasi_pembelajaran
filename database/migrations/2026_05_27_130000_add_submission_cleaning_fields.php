<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->longText('clean_text')->nullable()->after('extracted_at');
            $table->json('cleaning_result')->nullable()->after('clean_text');
            $table->string('cleaning_language')->nullable()->after('cleaning_result');
            $table->timestamp('cleaned_at')->nullable()->after('cleaning_language');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['clean_text', 'cleaning_result', 'cleaning_language', 'cleaned_at']);
        });
    }
};
