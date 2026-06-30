<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creation_collaboration_requests', function (Blueprint $table) {
            $table->dropForeign(['creation_id']);
            $table->foreignId('creation_id')->nullable()->change();
            $table->foreign('creation_id')->references('id')->on('creations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('creation_collaboration_requests', function (Blueprint $table) {
            $table->dropForeign(['creation_id']);
            $table->foreignId('creation_id')->nullable(false)->change();
            $table->foreign('creation_id')->references('id')->on('creations')->cascadeOnDelete();
        });
    }
};
