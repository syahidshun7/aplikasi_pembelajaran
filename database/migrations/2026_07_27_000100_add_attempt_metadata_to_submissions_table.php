<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('user_id');
            $table->boolean('reward_eligible')->default(true)->after('attempt_number');
        });

        $attempts = [];
        foreach (DB::table('submissions')->orderBy('quest_id')->orderBy('user_id')->orderBy('id')->get() as $submission) {
            $key = "{$submission->quest_id}:{$submission->user_id}";
            $attempts[$key] = ($attempts[$key] ?? 0) + 1;

            DB::table('submissions')
                ->where('id', $submission->id)
                ->update([
                    'attempt_number' => $attempts[$key],
                    'reward_eligible' => $attempts[$key] === 1,
                ]);
        }

        Schema::table('submissions', function (Blueprint $table) {
            $table->unique(['quest_id', 'user_id', 'attempt_number'], 'submissions_quest_user_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropUnique('submissions_quest_user_attempt_unique');
            $table->dropColumn(['attempt_number', 'reward_eligible']);
        });
    }
};
