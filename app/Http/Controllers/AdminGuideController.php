<?php

namespace App\Http\Controllers;// Sesuaikan dengan folder Admin kamu

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Models\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminGuideController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola materi.';

    /**
     * Menampilkan daftar materi (guides)
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:active,trash'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $view = (string) ($validated['view'] ?? 'active');

        $guideQuery = Guide::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->with('studyGroup:id,name,job_id');

        if ($this->isMentorUser()) {
            $mentorJobId = $this->requireMentorJobId();
            $guideQuery->whereHas('studyGroup', function ($query) use ($mentorJobId) {
                $query->withTrashed();
                $query->where('job_id', $mentorJobId);
            });
        }

        $studyGroupQuery = StudyGroup::query()->select('id', 'name')->orderBy('name');
        if ($this->isMentorUser()) {
            $studyGroupQuery->where('job_id', $this->requireMentorJobId());
        }

        return Inertia::render('Guide/Index', [
            'materi' => $guideQuery
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('studyGroup', function ($sq) use ($search) {
                                $sq->where('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString(),
            'studyGroups' => $studyGroupQuery->get(),
            'filters' => [
                'search' => $search,
                'view' => $view,
            ],
        ]);
    }

    /**
     * Menyimpan materi baru ke database
     */
    public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'study_group_id' => 'nullable|exists:study_groups,id',
        'file' => 'nullable|file|mimes:pdf,jpg,png,zip|max:10240',
    ]);

    $this->assertMentorCanManageStudyGroupId((int) $request->input('study_group_id'));

    $filePath = null;
    if ($request->hasFile('file')) {
        $filePath = $request->file('file')->store('guides', 'public');
    }

    // UUID akan terisi otomatis ke kolom 'uuid' berkat fungsi uniqueIds() di Model
    Guide::create([
        'title'       => $request->title,
        'description' => $request->description,
        'study_group_id' => $request->study_group_id,
        'file_path'   => $filePath, 
    ]);

    return back()->with('message', 'New Scroll has been inscribed in the library!');
}

    /**
     * Memperbarui materi yang sudah ada
     */
    public function update(Request $request, $uuid)
    {
        // Cari guide berdasarkan UUID karena rute manual mengirimkan UUID
        $guide = Guide::where('uuid', $uuid)->firstOrFail();
        $this->assertMentorCanAccessGuide($guide);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'file' => 'nullable|file|mimes:pdf,jpg,png,zip|max:10240',
        ]);

        $this->assertMentorCanManageStudyGroupId((int) $request->input('study_group_id'));

        $data = [
            'title'       => $request->title,
            'description' => $request->description,
            'study_group_id' => $request->study_group_id,
        ];

        if ($request->hasFile('file')) {
            // Hapus file lama dari storage jika user mengunggah file baru
            if ($guide->file_path) {
                Storage::disk('public')->delete($guide->file_path);
            }
            $data['file_path'] = $request->file('file')->store('guides', 'public');
        }

        $guide->update($data);

        return back()->with('message', 'The knowledge scroll has been updated.');
    }

    /**
     * Menghapus materi dari library
     */
    public function destroy($uuid): RedirectResponse
    {
        $guide = Guide::where('uuid', $uuid)->firstOrFail();
        $this->assertMentorCanAccessGuide($guide);

        $guide->delete();

        return back()->with('message', 'The scroll has been purged from the archive.');
    }

    public function restore(string $uuid): RedirectResponse
    {
        $guide = Guide::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertMentorCanAccessGuide($guide);

        $guide->restore();

        return back()->with('message', 'SCROLL_RESTORED');
    }

    public function forceDestroy(string $uuid): RedirectResponse
    {
        $guide = Guide::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertMentorCanAccessGuide($guide);

        if (!empty($guide->file_path) && Storage::disk('public')->exists($guide->file_path)) {
            Storage::disk('public')->delete($guide->file_path);
        }

        $guide->forceDelete();

        return back()->with('message', 'SCROLL_PERMANENTLY_DELETED');
    }

    private function isMentorUser(): bool
    {
        return (bool) auth()->user()?->isMentor();
    }

    private function requireMentorJobId(): int
    {
        $jobId = (int) (auth()->user()?->job_id ?? 0);
        abort_if($jobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);
        return $jobId;
    }

    private function assertMentorCanAccessGuide(Guide $guide): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        $groupJobId = (int) StudyGroup::withTrashed()
            ->whereKey((int) ($guide->study_group_id ?? 0))
            ->value('job_id');

        abort_unless($groupJobId === $mentorJobId, 403, 'MENTOR_CANNOT_ACCESS_GUIDE_OUTSIDE_JOB');
    }

    private function assertMentorCanManageStudyGroupId(int $studyGroupId): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();

        if ($studyGroupId <= 0) {
            abort(403, 'MENTOR_GUIDE_MUST_HAVE_STUDY_GROUP');
        }

        $isAllowed = StudyGroup::query()
            ->whereKey($studyGroupId)
            ->where('job_id', $mentorJobId)
            ->exists();

        abort_unless($isAllowed, 403, 'MENTOR_CANNOT_MANAGE_GUIDE_OUTSIDE_JOB');
    }
}
