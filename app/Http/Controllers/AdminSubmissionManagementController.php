<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminSubmissionManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,Pending,Approved,Rejected'],
            'duplicates' => ['nullable', 'in:0,1'],
        ]);

        $search = $validated['search'] ?? '';
        $status = $validated['status'] ?? 'all';
        $duplicates = $validated['duplicates'] ?? '0';

        $query = Submission::query()
            ->with([
                'user:id,name,username,email',
                'quest:id,title,uuid,difficulty',
            ])
            ->select('submissions.*')
            ->selectSub(function ($subQuery) {
                $subQuery->from('submissions as s2')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('s2.user_id', 'submissions.user_id')
                    ->whereColumn('s2.quest_id', 'submissions.quest_id');
            }, 'duplicate_count')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($q) use ($search) {
                    $q->where('submissions.content', 'like', "%{$search}%")
                        ->orWhere('submissions.uuid', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('quest', function ($qq) use ($search) {
                            $qq->where('title', 'like', "%{$search}%")
                                ->orWhere('uuid', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== 'all', function ($builder) use ($status) {
                $builder->where('submissions.status', $status);
            })
            ->when($duplicates === '1', function ($builder) {
                $builder->whereRaw(
                    '(SELECT COUNT(*) FROM submissions s2 WHERE s2.user_id = submissions.user_id AND s2.quest_id = submissions.quest_id) > 1'
                );
            })
            ->latest('submissions.id');

        $submissions = $query->paginate(20)->withQueryString();

        return Inertia::render('Submissions/Admin/Index', [
            'submissions' => $submissions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'duplicates' => $duplicates,
            ],
        ]);
    }

    public function update(Request $request, Submission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'status' => ['required', 'in:Pending,Approved,Rejected'],
            'grade' => ['nullable', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string'],
            'earned_exp' => ['nullable', 'integer', 'min:0'],
            'earned_gold' => ['nullable', 'integer', 'min:0'],
        ]);

        $status = $validated['status'];
        $grade = (int) ($validated['grade'] ?? 0);
        $earnedExp = (int) ($validated['earned_exp'] ?? 0);
        $earnedGold = (int) ($validated['earned_gold'] ?? 0);

        if ($status !== 'Approved') {
            $grade = $status === 'Rejected' ? $grade : 0;
            $earnedExp = 0;
            $earnedGold = 0;
        }

        $submission->update([
            'content' => $validated['content'],
            'status' => $status,
            'grade' => $grade,
            'feedback' => $validated['feedback'] ?? null,
            'earned_exp' => $earnedExp,
            'earned_gold' => $earnedGold,
        ]);

        $this->syncUserRewardTotals((int) $submission->user_id);

        return back()->with('message', 'SUBMISSION_UPDATED');
    }

    public function destroy(Submission $submission): RedirectResponse
    {
        $userId = (int) $submission->user_id;

        if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();
        $this->syncUserRewardTotals($userId);

        return back()->with('message', 'SUBMISSION_DELETED');
    }

    private function syncUserRewardTotals(int $userId): void
    {
        $totals = Submission::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['Approved', 'Rejected'])
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
