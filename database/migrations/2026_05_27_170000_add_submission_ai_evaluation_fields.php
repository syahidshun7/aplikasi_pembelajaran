<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('ai_evaluation_items')->nullable()->after('rubric_prepared_at');
            $table->json('ai_evaluation_result')->nullable()->after('ai_evaluation_items');
            $table->timestamp('ai_evaluated_at')->nullable()->after('ai_evaluation_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['ai_evaluation_items', 'ai_evaluation_result', 'ai_evaluated_at']);
        });
    }
};
