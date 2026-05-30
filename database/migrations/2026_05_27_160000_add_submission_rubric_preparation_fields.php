<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('rubric_preparation_items')->nullable()->after('semantic_enriched_at');
            $table->json('rubric_preparation_result')->nullable()->after('rubric_preparation_items');
            $table->timestamp('rubric_prepared_at')->nullable()->after('rubric_preparation_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['rubric_preparation_items', 'rubric_preparation_result', 'rubric_prepared_at']);
        });
    }
};
