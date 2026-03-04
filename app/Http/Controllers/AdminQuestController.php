<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AdminQuestController extends Controller
{
    /**
     * Menampilkan daftar semua submission untuk quest tertentu.
     */
    public function submissions(Request $request, Quest $quest)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,Pending,Approved,Rejected'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');

        $submissions = $this->buildQuestSubmissionsQuery($quest, $search, $status)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $downloadableFilesCount = (clone $this->buildQuestSubmissionsQuery($quest, $search, $status))
            ->whereNotNull('file_path')
            ->count();

        return Inertia::render('Quests/Admin/Submissions', [
            'quest' => $quest,
            'submissions' => $submissions,
            'downloadableFilesCount' => $downloadableFilesCount,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function downloadSubmissionFiles(Request $request, Quest $quest)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,Pending,Approved,Rejected'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');

        $submissions = $this->buildQuestSubmissionsQuery($quest, $search, $status)
            ->with('user:id,name,username')
            ->whereNotNull('file_path')
            ->latest('id')
            ->get();

        if ($submissions->isEmpty()) {
            return back()->withErrors([
                'download' => 'Tidak ada file submission yang bisa diunduh untuk filter saat ini.',
            ]);
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $slug = Str::slug((string) $quest->title, '-');
        $slug = $slug !== '' ? $slug : 'quest';
        $archiveFilename = "quest-submissions-{$slug}-" . now()->format('Ymd-His') . '.zip';
        $archivePath = $tempDir . DIRECTORY_SEPARATOR . $archiveFilename;

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors([
                'download' => 'Gagal membuat arsip file submission.',
            ]);
        }

        $addedCount = 0;
        $manifestRows = [];
        $manifestRows[] = 'submission_uuid,user_id,username,status,created_at,storage_path,archive_name';

        foreach ($submissions as $submission) {
            $storedPath = (string) $submission->file_path;
            if ($storedPath === '' || ! Storage::disk('public')->exists($storedPath)) {
                continue;
            }

            $absolutePath = Storage::disk('public')->path($storedPath);
            $extension = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));
            $extension = $extension !== '' ? $extension : 'bin';

            $username = trim((string) ($submission->user->username ?? $submission->user->name ?? 'user'));
            $username = Str::slug($username, '_');
            $username = $username !== '' ? $username : 'user_' . (int) $submission->user_id;

            $archiveName = sprintf(
                '%s/submission_%s_%s.%s',
                strtolower((string) $submission->status ?: 'pending'),
                (string) $submission->uuid,
                $username,
                $extension
            );

            if ($zip->addFile($absolutePath, $archiveName)) {
                $addedCount++;
                $manifestRows[] = implode(',', [
                    (string) $submission->uuid,
                    (string) $submission->user_id,
                    $username,
                    (string) $submission->status,
                    (string) $submission->created_at,
                    $storedPath,
                    $archiveName,
                ]);
            }
        }

        if ($addedCount === 0) {
            $zip->close();
            @unlink($archivePath);

            return back()->withErrors([
                'download' => 'Tidak ada file fisik submission yang ditemukan di storage.',
            ]);
        }

        $zip->addFromString('manifest.csv', implode("\n", $manifestRows) . "\n");
        $zip->close();

        return response()->download($archivePath, $archiveFilename)->deleteFileAfterSend(true);
    }

    private function buildQuestSubmissionsQuery(Quest $quest, string $search, string $status)
    {
        return $quest->submissions()
            ->with('user')
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('content', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });
    }
}
