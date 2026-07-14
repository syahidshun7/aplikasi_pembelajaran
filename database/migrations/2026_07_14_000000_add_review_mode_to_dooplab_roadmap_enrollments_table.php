<?php

use App\Models\DoopLabRoadmapEnrollment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_roadmap_enrollments', function (Blueprint $table): void {
            if (! Schema::hasColumn('dooplab_roadmap_enrollments', 'review_mode')) {
                $table->string('review_mode', 20)
                    ->default(DoopLabRoadmapEnrollment::REVIEW_MODE_MANUAL)
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_roadmap_enrollments', function (Blueprint $table): void {
            if (Schema::hasColumn('dooplab_roadmap_enrollments', 'review_mode')) {
                $table->dropColumn('review_mode');
            }
        });
    }
};
