<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_logbook_entries', function (Blueprint $table) {
            $table->json('documentation_paths')->nullable()->after('documentation_path');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_logbook_entries', function (Blueprint $table) {
            $table->dropColumn('documentation_paths');
        });
    }
};
