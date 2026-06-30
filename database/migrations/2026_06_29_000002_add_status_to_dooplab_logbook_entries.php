<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_logbook_entries', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_logbook_entries', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
