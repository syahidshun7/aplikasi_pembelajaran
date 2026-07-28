<?php

namespace App\Http\Controllers;// Sesuaikan dengan folder Admin kamu

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Models\StudyGroup;
use App\Services\StudyGroupStaffAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminGuideController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola materi.';

    /**
     * Menampilkan daftar materi (guides)
     */
    public function index(Request $request, ?string $groupUuid = null)
    {
        $scopedGroup = $this->resolveScopedStudyGroup($request, $groupUuid);
        $this->abortNonSuperAdminGlobalIndex($request, $scopedGroup);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:active,trash'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $view = (string) ($validated['view'] ?? 'active');

        $guideQuery = Guide::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->when($scopedGroup, fn ($query) => $query->where('study_group_id', (int) $scopedGroup->id))
            ->with('studyGroup:id,uuid,name,job_id');

        if ($this->isMentorUser() && ! $scopedGroup) {
            $mentorJobId = $this->requireMentorJobId();
            $guideQuery->whereHas('studyGroup', function ($query) use ($mentorJobId) {
                $query->withTrashed();
                $query->where('job_id', $mentorJobId);
            });
        }

        $studyGroupQuery = StudyGroup::query()->select('id', 'name')->orderBy('name');
        if ($scopedGroup) {
            $studyGroupQuery->whereKey((int) $scopedGroup->id);
        }
        if ($this->isMentorUser() && ! $scopedGroup) {
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
            'selectedStudyGroup' => $scopedGroup ? [
                'uuid' => (string) $scopedGroup->uuid,
                'id' => (int) $scopedGroup->id,
                'name' => (string) $scopedGroup->name,
                'back_url' => route('groups.detail', $scopedGroup->uuid),
                'guides_url' => route('groups.guides.index', $scopedGroup->uuid),
            ] : null,
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
        'content_source' => 'nullable|in:file,google_docs,video',
        'google_docs_url' => 'nullable|string|max:2048|url',
        'video_url' => 'nullable|string|max:2048|url',
        'file' => 'nullable|file|mimes:pdf,jpg,png,zip|max:10240',
    ]);

    $this->assertMentorCanManageStudyGroupId((int) $request->input('study_group_id'));

    $contentSource = (string) $request->input('content_source', 'file');
    $googleDocsEmbedUrl = $contentSource === 'google_docs'
        ? $this->normalizeGoogleDocsEmbedUrl((string) $request->input('google_docs_url', ''))
        : null;
    $videoEmbedUrl = $contentSource === 'video'
        ? $this->normalizeVideoEmbedUrl((string) $request->input('video_url', ''))
        : null;

    if ($contentSource === 'google_docs' && empty($googleDocsEmbedUrl)) {
        throw ValidationException::withMessages([
            'google_docs_url' => 'Google Docs URL wajib diisi saat source dipilih sebagai embed.',
        ]);
    }

    if ($contentSource === 'video' && empty($videoEmbedUrl)) {
        throw ValidationException::withMessages([
            'video_url' => 'URL video YouTube atau Google Drive wajib diisi.',
        ]);
    }

    $useGoogleDocsSource = $contentSource === 'google_docs' && !empty($googleDocsEmbedUrl);
    $useVideoSource = $contentSource === 'video' && !empty($videoEmbedUrl);

    $filePath = null;
    if (! $useGoogleDocsSource && ! $useVideoSource && $request->hasFile('file')) {
        $filePath = $request->file('file')->store('guides', 'public');
    }

    // UUID akan terisi otomatis ke kolom 'uuid' berkat fungsi uniqueIds() di Model
    Guide::create([
        'title'       => $request->title,
        'description' => $request->description,
        'study_group_id' => $request->study_group_id,
        'file_path'   => $filePath,
        'google_docs_embed_url' => $useGoogleDocsSource ? $googleDocsEmbedUrl : null,
        'video_embed_url' => $useVideoSource ? $videoEmbedUrl : null,
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
            'content_source' => 'nullable|in:file,google_docs,video',
            'google_docs_url' => 'nullable|string|max:2048|url',
            'video_url' => 'nullable|string|max:2048|url',
            'file' => 'nullable|file|mimes:pdf,jpg,png,zip|max:10240',
        ]);

        $this->assertMentorCanManageStudyGroupId((int) $request->input('study_group_id'));

        $contentSource = (string) $request->input('content_source', 'file');
        $googleDocsEmbedUrl = $contentSource === 'google_docs'
            ? $this->normalizeGoogleDocsEmbedUrl((string) $request->input('google_docs_url', ''))
            : null;
        $videoEmbedUrl = $contentSource === 'video'
            ? $this->normalizeVideoEmbedUrl((string) $request->input('video_url', ''))
            : null;

        if ($contentSource === 'google_docs' && empty($googleDocsEmbedUrl)) {
            throw ValidationException::withMessages([
                'google_docs_url' => 'Google Docs URL wajib diisi saat source dipilih sebagai embed.',
            ]);
        }

        if ($contentSource === 'video' && empty($videoEmbedUrl)) {
            throw ValidationException::withMessages([
                'video_url' => 'URL video YouTube atau Google Drive wajib diisi.',
            ]);
        }

        $useGoogleDocsSource = $contentSource === 'google_docs' && !empty($googleDocsEmbedUrl);
        $useVideoSource = $contentSource === 'video' && !empty($videoEmbedUrl);

        $data = [
            'title'       => $request->title,
            'description' => $request->description,
            'study_group_id' => $request->study_group_id,
        ];

        if ($useGoogleDocsSource) {
            if ($guide->file_path) {
                Storage::disk('public')->delete($guide->file_path);
            }
            $data['file_path'] = null;
            $data['google_docs_embed_url'] = $googleDocsEmbedUrl;
            $data['video_embed_url'] = null;
        } elseif ($useVideoSource) {
            if ($guide->file_path) {
                Storage::disk('public')->delete($guide->file_path);
            }
            $data['file_path'] = null;
            $data['google_docs_embed_url'] = null;
            $data['video_embed_url'] = $videoEmbedUrl;
        } else {
            $data['google_docs_embed_url'] = null;
            $data['video_embed_url'] = null;

            if ($request->hasFile('file')) {
                if ($guide->file_path) {
                    Storage::disk('public')->delete($guide->file_path);
                }
                $data['file_path'] = $request->file('file')->store('guides', 'public');
            }
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

    private function resolveScopedStudyGroup(Request $request, ?string $groupUuid): ?StudyGroup
    {
        $groupUuid = trim((string) ($groupUuid ?? ''));
        if ($groupUuid === '') {
            return null;
        }

        $group = StudyGroup::query()->where('uuid', $groupUuid)->firstOrFail();
        abort_unless(
            app(StudyGroupStaffAccessService::class)->canAccess($request->user(), $group),
            403,
            'STUDY_GROUP_STAFF_ACCESS_DENIED'
        );

        return $group;
    }

    private function abortNonSuperAdminGlobalIndex(Request $request, ?StudyGroup $scopedGroup): void
    {
        if ($scopedGroup) {
            return;
        }

        abort_unless(
            (string) ($request->user()?->role ?? '') === \App\Models\User::ROLE_SUPER_ADMIN,
            403,
            'SUPER_ADMIN_ONLY_GLOBAL_GUIDE_INDEX'
        );
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

        $group = StudyGroup::withTrashed()->find((int) ($guide->study_group_id ?? 0));

        abort_unless($group && app(StudyGroupStaffAccessService::class)->canAccess(auth()->user(), $group), 403, 'MENTOR_CANNOT_ACCESS_GUIDE_OUTSIDE_GROUP');
    }

    private function assertMentorCanManageStudyGroupId(int $studyGroupId): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        if ($studyGroupId <= 0) {
            abort(403, 'MENTOR_GUIDE_MUST_HAVE_STUDY_GROUP');
        }

        $group = StudyGroup::query()->find($studyGroupId);
        $isAllowed = $group && app(StudyGroupStaffAccessService::class)->canAccess(auth()->user(), $group);

        abort_unless($isAllowed, 403, 'MENTOR_CANNOT_MANAGE_GUIDE_OUTSIDE_GROUP');
    }

    private function normalizeGoogleDocsEmbedUrl(string $url): ?string
    {
        $trimmedUrl = trim($url);
        if ($trimmedUrl === '') {
            return null;
        }

        $parts = parse_url($trimmedUrl);
        if (! is_array($parts)) {
            throw ValidationException::withMessages([
                'google_docs_url' => 'Format Google Docs URL tidak valid.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        if ($host === 'docs.google.com') {
            if (preg_match('#^/(document|spreadsheets|presentation)/d/([^/]+)#', $path, $matches) === 1) {
                $docType = $matches[1];
                $docId = $matches[2];

                if ($docType === 'presentation') {
                    return "https://docs.google.com/presentation/d/{$docId}/embed";
                }

                return "https://docs.google.com/{$docType}/d/{$docId}/preview";
            }

            throw ValidationException::withMessages([
                'google_docs_url' => 'Google Docs URL harus berupa link Document, Spreadsheet, atau Presentation yang valid.',
            ]);
        }

        if ($host === 'drive.google.com') {
            if (preg_match('#^/file/d/([^/]+)#', $path, $matches) === 1) {
                return "https://drive.google.com/file/d/{$matches[1]}/preview";
            }

            $openId = (string) ($query['id'] ?? '');
            if ($path === '/open' && $openId !== '') {
                return "https://drive.google.com/file/d/{$openId}/preview";
            }

            throw ValidationException::withMessages([
                'google_docs_url' => 'Google Drive URL harus berupa link file yang valid.',
            ]);
        }

        throw ValidationException::withMessages([
            'google_docs_url' => 'Hanya URL Google Docs/Google Drive yang didukung untuk embed.',
        ]);
    }

    private function normalizeVideoEmbedUrl(string $url): ?string
    {
        $trimmedUrl = trim($url);
        if ($trimmedUrl === '') {
            return null;
        }

        $parts = parse_url($trimmedUrl);
        if (! is_array($parts)) {
            throw ValidationException::withMessages([
                'video_url' => 'Format URL video tidak valid.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $youtubeId = null;
        if ($host === 'youtu.be') {
            $youtubeId = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {
                $youtubeId = (string) ($query['v'] ?? '');
            } elseif (preg_match('#^/(?:shorts|embed)/([^/]+)#', $path, $matches) === 1) {
                $youtubeId = $matches[1];
            }
        }

        if ($youtubeId !== null && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $youtubeId) === 1) {
            return "https://www.youtube-nocookie.com/embed/{$youtubeId}";
        }

        if ($host === 'drive.google.com') {
            if (preg_match('#^/file/d/([^/]+)#', $path, $matches) === 1) {
                return "https://drive.google.com/file/d/{$matches[1]}/preview";
            }

            $openId = (string) ($query['id'] ?? '');
            if ($path === '/open' && $openId !== '') {
                return "https://drive.google.com/file/d/{$openId}/preview";
            }
        }

        throw ValidationException::withMessages([
            'video_url' => 'Gunakan link video YouTube atau link file Google Drive yang valid.',
        ]);
    }
}
