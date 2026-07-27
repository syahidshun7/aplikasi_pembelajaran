<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->uuid('client_submission_token')->nullable()->after('reward_eligible')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropUnique(['client_submission_token']);
            $table->dropColumn('client_submission_token');
        });
    }
};
