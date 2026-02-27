<?php

namespace App\Http\Controllers;
use App\Models\Submission;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    public function store(Request $request, Quest $quest)
    {
        $request->validate([
            'content' => 'required|string',
            'file' => 'nullable', // Max 2MB
        ]);

        $submission = Submission::where('quest_id', $quest->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        $isUpdate = (bool) $submission;
        $filePath = $submission?->file_path;

        if (! $submission) {
            $submission = new Submission();
            $submission->quest_id = $quest->id;
            $submission->user_id = auth()->id();
        }

        if ($request->hasFile('file')) {
            // Hapus file lama agar storage tidak menumpuk.
            if ($isUpdate && $submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }

            $filePath = $request->file('file')->store('submissions', 'public');
        }

        $wasApproved = $submission->status === 'Approved';

        $submission->content = $request->content;
        $submission->file_path = $filePath;
        $submission->status = 'Pending';
        $submission->grade = 0;
        $submission->feedback = null;
        $submission->earned_exp = 0;
        $submission->earned_gold = 0;
        $submission->scores_detail = null;
        $submission->save();

        if ($wasApproved) {
            $this->syncUserRewardTotals((int) $submission->user_id);
        }

        // Opsional: Ubah status quest menjadi Ongoing saat ada submission masuk
        // $quest->update(['status' => 'Ongoing']);

        return back()->with('message', $isUpdate
            ? 'MISSION_REPORT_UPDATED_RE-EVALUATING'
            : 'MISSION_REPORT_SENT_WAITING_FOR_REVIEW');
    }

    
    public function showSubmission(Submission $submission) // Laravel otomatis mencari data berdasarkan ID
{
    // Cek izin lewat Policy 'view'
    $this->authorize('view', $submission);

    return Inertia::render('Quests/SubmissionDetail', [
        'submission' => [
            'id' => $submission->id,
            'uuid' => $submission->uuid,
            'status' => $submission->status,
            'content' => $submission->content,
            'file_path' => $submission->file_path,
            'feedback' => $submission->feedback,
            'submitted_at' => $submission->created_at->format('d M Y | H:i'),
            'quest' => $submission->quest,
            'grade' => $submission->grade,
            'earned_exp' => (int) ($submission->earned_exp ?? 0),
            'earned_gold' => (int) ($submission->earned_gold ?? 0),
        ]
    ]);
}
public function update(Request $request, $uuid)
{
    // 1. Cari data berdasarkan UUID (karena dari Vue kirim UUID)
    $submission = Submission::where('uuid', $uuid)->firstOrFail();

    if ((int) $submission->user_id !== (int) auth()->id()) {
        abort(403);
    }

    $wasApproved = $submission->status === 'Approved';

    // 2. Validasi (Sama dengan store)
    $request->validate([
        'content' => 'required|string',
        'file' => 'nullable', // Sesuaikan dengan store kamu
    ]);

    // 3. Siapkan Data Dasar
    $submission->content = $request->content;
    $submission->status = 'Pending'; // Reset status supaya direview lagi
    $submission->grade = 0;
    $submission->feedback = null;
    $submission->earned_exp = 0;
    $submission->earned_gold = 0;
    $submission->scores_detail = null;

    // 4. Logika File (Gaya identik dengan store kamu)
    if ($request->hasFile('file')) {
        // Hapus file lama jika ada agar tidak jadi sampah
        if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
            Storage::disk('public')->delete($submission->file_path);
        }

        // Simpan file baru (Sama persis logic store kamu)
        $submission->file_path = $request->file('file')->store('submissions', 'public');
    }

    $submission->save();

    if ($wasApproved) {
        $this->syncUserRewardTotals((int) $submission->user_id);
    }

    return back()->with('message', 'MISSION_REPORT_UPDATED_RE-EVALUATING');
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
