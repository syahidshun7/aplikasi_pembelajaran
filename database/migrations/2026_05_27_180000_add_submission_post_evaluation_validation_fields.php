<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('post_evaluation_validation_items')->nullable()->after('ai_evaluated_at');
            $table->json('post_evaluation_validation_result')->nullable()->after('post_evaluation_validation_items');
            $table->timestamp('post_evaluation_validated_at')->nullable()->after('post_evaluation_validation_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'post_evaluation_validation_items',
                'post_evaluation_validation_result',
                'post_evaluation_validated_at',
            ]);
        });
    }
};
