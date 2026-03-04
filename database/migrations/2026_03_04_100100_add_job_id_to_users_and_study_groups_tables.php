<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('job_id')
                ->nullable()
                ->after('role')
                ->constrained('job_roles')
                ->nullOnDelete();
        });

        Schema::table('study_groups', function (Blueprint $table) {
            $table->foreignId('job_id')
                ->nullable()
                ->after('max_members')
                ->constrained('job_roles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('study_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_id');
        });
    }
};

