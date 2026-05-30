<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('result_presentation_items')->nullable()->after('post_evaluation_validated_at');
            $table->json('result_presentation_result')->nullable()->after('result_presentation_items');
            $table->timestamp('result_presented_at')->nullable()->after('result_presentation_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'result_presentation_items',
                'result_presentation_result',
                'result_presented_at',
            ]);
        });
    }
};
