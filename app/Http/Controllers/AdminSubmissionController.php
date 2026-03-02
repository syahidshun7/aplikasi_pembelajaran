<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AdminSubmissionController extends Controller
{
    /**
     * Halaman inspeksi detail untuk satu submission.
     */
    public function inspect(Submission $submission)
    {
        // Pastikan relasi terload agar Vue tidak membaca 'undefined'
        $submission->load(['user', 'quest']);

        return Inertia::render('Quests/Admin/Inspect', [
            'submission' => $submission
        ]);
    }

    /**
     * Memproses penilaian (Verdict) dan memberikan reward Gold/EXP.
     */
 public function verdict(Request $request, Submission $submission)
{
    $request->validate([
        'final_score' => 'required|numeric|min:0|max:100',
        'feedback'    => 'nullable|string',
        'status'      => 'required|in:Approved,Rejected',
        'scores_detail' => 'nullable|array',
    ]);

    $quest = $submission->quest;
    $newScore   = (int) $request->final_score;
    $newPortion = $newScore / 100;

    $finalGold = 0;
    $finalExp  = 0;

    if ($request->status === 'Approved') {
        $questExp = (int) ($quest->reward_exp ?? 0);
        if ($questExp <= 0) {
            // Fallback untuk data quest lama yang belum punya reward_exp valid.
            $questExp = (int) ($quest->reward_gold ?? 0);
        }

        $finalGold = (int) floor($quest->reward_gold * $newPortion);
        $finalExp  = (int) floor($questExp * $newPortion);
    }

    $submission->update([
        'grade'    => $newScore,
        'feedback' => $request->feedback,
        'status'   => $request->status,
        'earned_gold' => $finalGold,
        'earned_exp' => $finalExp,
        'scores_detail' => $request->input('scores_detail'),
    ]);

    $this->syncUserRewardTotals((int) $submission->user_id);

    return redirect()->back()->with('message', 'Verdict Processed & Rewards Calculated!');
}
    /**
     * Fitur AI Advisor (Simulasi)
     */
    public function checkWithAI(Submission $submission)
    {
        $submission->load('quest');

        $questTitle = $submission->quest->title;
        $studentWork = $submission->content ?? '';
        $workLength = strlen($studentWork);

        // Inisialisasi Score
        $scoreFunc = 0;
        $scoreLogic = 0;
        $scoreClean = 0;
        $aiFeedback = "";

        // Logika Analisis Sederhana
        if ($workLength < 15) {
            $scoreFunc = 10;
            $scoreLogic = 10;
            $scoreClean = 20;
            $aiFeedback = "CRITICAL_FAILURE: Bukti pengerjaan terlalu minim. Sistem mendeteksi kemungkinan bypass quest.";
        } elseif ($workLength < 100) {
            $scoreFunc = 65;
            $scoreLogic = 60;
            $scoreClean = 75;
            $aiFeedback = "ANALYSIS_PARTIAL: Implementasi dasar ditemukan untuk '{$questTitle}'. Namun, penjelasan atau struktur kode masih bisa ditingkatkan.";
        } else {
            $scoreFunc = rand(85, 100);
            $scoreLogic = rand(80, 95);
            $scoreClean = rand(85, 100);
            $aiFeedback = "ANALYSIS_SUCCESS: Data artefak untuk '{$questTitle}' tervalidasi. Struktur logika efisien dan memenuhi standar Adventurer Guild.";
        }

        return response()->json([
            'status' => 'success',
            'scores' => [
                'func' => $scoreFunc,
                'logic' => $scoreLogic,
                'neat' => $scoreClean,
                'extra' => 0,
                'att' => 0,
            ],
            'func'   => $scoreFunc,
            'logic'  => $scoreLogic,
            'clean'  => $scoreClean,
            'feedback' => $aiFeedback,
        ]);
    }

    private function syncUserRewardTotals(int $userId): void
    {
        $totals = Submission::query()
            ->where('user_id', $userId)
            ->where('status', 'Approved')
            ->selectRaw('COALESCE(SUM(earned_exp),0) as exp_total, COALESCE(SUM(earned_gold),0) as gold_total')
            ->first();

        $newExp = (int) ($totals->exp_total ?? 0);
        $newGold = (int) ($totals->gold_total ?? 0);

        $updateData = [
            'exp' => $newExp,
            'gold' => $newGold,
        ];

        if (Schema::hasColumn('users', 'lvl')) {
            $updateData['lvl'] = (int) floor($newExp / 1000) + 1;
        } elseif (Schema::hasColumn('users', 'level')) {
            $updateData['level'] = (int) floor($newExp / 1000) + 1;
        }

        User::query()->whereKey($userId)->update($updateData);
    }
}
