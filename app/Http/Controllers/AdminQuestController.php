<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Submission;
use App\Models\Quest;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminQuestController extends Controller
{
    public function submissions(Quest $quest)
    {
        // Mengambil quest beserta relasi submissions dan user yang mengerjakannya
        $submissions = $quest->submissions()
            ->with('user') // Pastikan model Submission punya relasi belongsTo(User)
            ->latest()
            ->get();

        return Inertia::render('Quests/Admin/Submissions', [
            'quest' => $quest,
            'submissions' => $submissions
        ]);
    }

    public function inspect($id)
{
    // Mengambil data submission beserta user yang mengerjakan dan quest-nya
    // Kita gunakan with() agar data difficulty dan reward_gold tersedia
    $submission = \App\Models\Submission::with(['user', 'quest'])->findOrFail($id);

    return \Inertia\Inertia::render('Quests/Admin/Inspect', [
        'submission' => $submission
    ]);
}

    public function verdict(Request $request, Submission $submission)
    {
        // ... (Validasi tetap sama) ...

        $user = $submission->user;
        $quest = $submission->quest;
        $newScore = $request->final_score;
        $oldScore = $submission->grade;

        // 2. SESUAIKAN DENGAN FORMAT DB (S-Rank, A-Rank, dst)
        $difficultyMultipliers = [
            'S-Rank' => 3.0, // Multiplier tertinggi
            'A-Rank' => 2.0,
            'B-Rank' => 1.5,
            'C-Rank' => 1.0,
            'D-Rank' => 0.8,
        ];

        // Mengambil multiplier berdasarkan kolom difficulty di tabel quests
        $mult = $difficultyMultipliers[$quest->difficulty] ?? 1.0;

        DB::transaction(function () use ($request, $submission, $user, $quest, $newScore, $oldScore, $mult) {

            // --- LOGIKA PEMBATALAN (UNDO) ---
            if ($oldScore > 0) {
                $oldPortion = $oldScore / 100;
                // Rumus: Base * (Skor/100) * Multiplier Rank
                $oldGold = floor($quest->reward_gold * $oldPortion * $mult);
                $oldExp  = floor(100 * $oldPortion * $mult);

                $user->decrement('gold', $oldGold);
                $user->decrement('exp', $oldExp);
            }

            // --- LOGIKA PEMBERIAN REWARD BARU ---
            $newPortion = $newScore / 100;

            $finalGold = floor($quest->reward_gold * $newPortion * $mult);
            $finalExp  = floor(100 * $newPortion * $mult);

            $user->increment('gold', $finalGold);
            $user->increment('exp', $finalExp);

            // --- UPDATE DATA ---
            $submission->update([
                'grade' => $newScore,
                'feedback' => $request->feedback,
                'status' => $newScore >= 50 ? 'Approved' : 'Rejected'
            ]);

            // Auto Level Up
            $user->refresh();
            $user->update(['level' => floor($user->exp / 1000) + 1]);
        });

        return redirect()->back()->with('message', 'Verdict Synchronized with ' . $quest->difficulty . ' multiplier!');
    }
public function checkWithAI(Submission $submission)
{
    // 1. Ambil Data Konteks (Simulasi AI butuh data ini)
    $questTitle = $submission->quest->title;
    $questDesc = $submission->quest->description;
    $studentWork = $submission->content ?? '';
    
    // 2. LOGIKA SIMULASI (Seolah-olah AI sedang berpikir)
    // Kita buat simulasi penilaian berdasarkan kata kunci dan panjang konten
    
    $scoreFunc = 0;
    $scoreLogic = 0;
    $scoreClean = 0;
    $aiFeedback = "";

    // Simulasi: Jika konten mengandung kata kunci dari deskripsi quest (dummy logic)
    $workLength = strlen($studentWork);
    
    if ($workLength < 10) {
        $scoreFunc = 20;
        $scoreLogic = 10;
        $scoreClean = 30;
        $aiFeedback = "ANALYSIS_FAILED: Konten terlalu singkat. Adventurer tidak memberikan bukti yang cukup untuk quest '{$questTitle}'.";
    } elseif ($workLength < 50) {
        $scoreFunc = 60;
        $scoreLogic = 50;
        $scoreClean = 70;
        $aiFeedback = "ANALYSIS_PARTIAL: Solusi ditemukan namun kurang mendalam. Logika dasar sudah benar tapi optimasi diperlukan.";
    } else {
        $scoreFunc = rand(85, 100); // Simulasi skor acak untuk hasil bagus
        $scoreLogic = rand(75, 95);
        $scoreClean = rand(80, 100);
        $aiFeedback = "ANALYSIS_SUCCESS: Luar biasa! Kode untuk '{$questTitle}' sangat rapi. Fungsionalitas sesuai dengan deskripsi quest. Pertahankan kualitas ini!";
    }

    // 3. RETURN JSON (Format yang ditunggu oleh Inspect.vue)
    return response()->json([
        'status' => 'success',
        'func'   => $scoreFunc,
        'logic'  => $scoreLogic,
        'clean'  => $scoreClean,
        'feedback' => $aiFeedback,
        'meta'   => [
            'quest_context' => $questTitle,
            'difficulty'    => $submission->quest->difficulty
        ]
    ]);
}
}
