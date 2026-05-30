<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('structured_items')->nullable()->after('cleaned_at');
            $table->json('structure_result')->nullable()->after('structured_items');
            $table->timestamp('structure_detected_at')->nullable()->after('structure_result');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['structured_items', 'structure_result', 'structure_detected_at']);
        });
    }
};
