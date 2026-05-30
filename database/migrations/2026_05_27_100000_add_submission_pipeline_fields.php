<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('submission_id')->nullable()->unique()->after('uuid');
            $table->string('pipeline_status')->default('pending_preprocessing')->after('status');
            $table->boolean('preprocess_started')->default(false)->after('pipeline_status');
            $table->string('file_type')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['submission_id', 'pipeline_status', 'preprocess_started', 'file_type']);
        });
    }
};
