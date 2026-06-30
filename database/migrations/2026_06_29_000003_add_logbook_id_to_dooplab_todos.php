<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_todos', function (Blueprint $table) {
            $table->foreignId('logbook_id')
                ->nullable()
                ->after('creation_id')
                ->constrained('dooplab_logbooks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_todos', function (Blueprint $table) {
            $table->dropForeign(['logbook_id']);
            $table->dropColumn('logbook_id');
        });
    }
};
