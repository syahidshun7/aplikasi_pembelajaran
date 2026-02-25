<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    $user  = $submission->user;
    $quest = $submission->quest;

    // Kita tidak perlu lagi $multipliers di sini karena $quest->reward_gold 
    // sudah dikunci berdasarkan Rank saat Quest dibuat. 
    // Cukup gunakan $quest->reward_gold sebagai Max Reward.

    DB::transaction(function () use ($request, $submission, $user, $quest) {
        
        // 1. ROLLBACK REWARD LAMA (Hanya jika status sebelumnya Approved)
        // Kita gunakan pengecekan status, bukan grade > 0 agar lebih akurat
        if ($submission->status === 'Approved') {
            $oldGold = (int) ($submission->earned_gold ?? 0);
            $oldExp  = (int) ($submission->earned_exp ?? 0);

            // Gunakan max(0, ...) agar gold tidak minus jika admin salah input
            if ($oldGold > 0) {
                $user->decrement('gold', $oldGold);
            }
            if ($oldExp > 0) {
                $user->decrement('exp', $oldExp);
            }
        }

        // 2. HITUNG REWARD BARU (Hanya jika status Approved)
        $newScore   = $request->final_score;
        $newPortion = $newScore / 100;
        
        $finalGold = 0;
        $finalExp  = 0;

        if ($request->status === 'Approved') {
            $finalGold  = floor($quest->reward_gold * $newPortion);
            $finalExp   = floor(1000 * $newPortion); // Samakan basis dengan rollback

            // 3. UPDATE USER
            $user->increment('gold', $finalGold);
            $user->increment('exp', $finalExp);
        }

        // 4. UPDATE SUBMISSION
        $submission->update([
            'grade'    => $newScore,
            'feedback' => $request->feedback,
            'status'   => $request->status,
            'earned_gold' => $finalGold,
            'earned_exp' => $finalExp,
            'scores_detail' => $request->input('scores_detail'),
        ]);

        // 5. UPDATE LEVEL (Logic: Setiap 1000 EXP naik 1 Level)
        $user->refresh();
        $newLevel = floor($user->exp / 1000) + 1;
        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : 'level';
        $user->update([$levelColumn => $newLevel]);
    });

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
}
