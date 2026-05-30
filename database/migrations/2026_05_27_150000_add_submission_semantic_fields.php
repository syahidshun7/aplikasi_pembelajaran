<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('semantic_items')->nullable()->after('structure_detected_at');
            $table->json('semantic_result')->nullable()->after('semantic_items');
            $table->timestamp('semantic_enriched_at')->nullable()->after('semantic_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['semantic_items', 'semantic_result', 'semantic_enriched_at']);
        });
    }
};
