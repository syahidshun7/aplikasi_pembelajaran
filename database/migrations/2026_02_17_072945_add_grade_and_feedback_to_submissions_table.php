<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Kita letakkan setelah kolom status agar rapi saat dilihat di DB
            $table->string('grade')->nullable()->after('status');
            $table->text('feedback')->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['grade', 'feedback']);
        });
    }
};