<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan kolom earned_exp dan earned_gold ke tabel submissions
        Schema::table('submissions', function (Blueprint $table) {
            $table->integer('earned_exp')->default(0)->after('status');
            $table->integer('earned_gold')->default(0)->after('earned_exp');
        });

        // 2. Ambil data lama dari tabel submissions, join ke quests untuk ambil reward_gold
        $submissions = DB::table('submissions')
            ->join('quests', 'submissions.quest_id', '=', 'quests.id')
            ->select('submissions.id', 'submissions.grade', 'quests.reward_gold')
            ->get();

        // 3. Update setiap baris submission dengan hasil perhitungan
        foreach ($submissions as $sub) {
            // Karena EXP dan Gold nilainya sama (berdasarkan reward_gold)
            // Rumus: (grade / 100) * reward_gold
            $multiplier = ($sub->grade ?? 0) / 100;
            $calculatedValue = round($sub->reward_gold * $multiplier);

            DB::table('submissions')
                ->where('id', $sub->id)
                ->update([
                    'earned_exp' => $calculatedValue,
                    'earned_gold' => $calculatedValue
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['earned_exp', 'earned_gold']);
        });
    }
};