<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->boolean('is_open_for_collaboration')
                ->default(false)
                ->after('is_public');

            $table->index('is_open_for_collaboration');
        });
    }

    public function down(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->dropIndex(['is_open_for_collaboration']);
            $table->dropColumn('is_open_for_collaboration');
        });
    }
};
