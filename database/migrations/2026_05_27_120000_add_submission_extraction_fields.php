<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->longText('extracted_text')->nullable()->after('file_type');
            $table->json('extraction_result')->nullable()->after('extracted_text');
            $table->timestamp('extracted_at')->nullable()->after('extraction_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['extracted_text', 'extraction_result', 'extracted_at']);
        });
    }
};
