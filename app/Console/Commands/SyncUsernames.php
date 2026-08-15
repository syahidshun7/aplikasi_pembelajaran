<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncUsernames extends Command
{
    protected $signature = 'users:sync-usernames
        {--apply : Simpan perubahan ke database. Tanpa flag ini hanya dry-run.}
        {--include-deleted : Ikut memproses user yang soft-deleted.}';

    protected $description = 'Normalize existing usernames by replacing spaces with underscores, removing emoji/invalid characters, and resolving duplicates.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $includeDeleted = (bool) $this->option('include-deleted');
        $usedUsernames = [];
        $changes = [];

        $query = User::query()
            ->select(['id', 'name', 'username'])
            ->orderBy('id');

        if ($includeDeleted && method_exists(User::class, 'withTrashed')) {
            $query->withTrashed();
        }

        $users = $query->get();

        foreach ($users as $user) {
            $currentUsername = (string) ($user->username ?? '');
            $baseUsername = $this->normalizeUsername($currentUsername);

            if ($baseUsername === '') {
                $baseUsername = $this->normalizeUsername((string) ($user->name ?? ''));
            }

            if ($baseUsername === '') {
                $baseUsername = 'user_' . $user->id;
            }

            $nextUsername = $this->uniqueUsername($baseUsername, $usedUsernames, (int) $user->id);
            $usedUsernames[$nextUsername] = true;

            if ($currentUsername !== $nextUsername) {
                $changes[] = [
                    'id' => (int) $user->id,
                    'name' => (string) ($user->name ?? ''),
                    'from' => $currentUsername,
                    'to' => $nextUsername,
                ];
            }
        }

        if ($changes === []) {
            $this->info('Semua username sudah sesuai format.');
            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Before', 'After'], array_map(
            fn (array $change) => [$change['id'], $change['name'], $change['from'], $change['to']],
            $changes,
        ));

        if (! $apply) {
            $this->warn('DRY-RUN: belum ada data yang disimpan. Jalankan dengan --apply untuk menerapkan perubahan.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                User::query()
                    ->whereKey($change['id'])
                    ->update(['username' => $change['to']]);
            }
        });

        $this->info('Selesai. ' . count($changes) . ' username berhasil disinkronkan.');

        return self::SUCCESS;
    }

    private function normalizeUsername(string $username): string
    {
        $username = Str::lower(trim($username));
        $username = preg_replace('/\s+/u', '_', $username) ?? '';
        $username = preg_replace('/[^a-z0-9._-]+/u', '', $username) ?? '';
        $username = preg_replace('/[._-]{2,}/', '_', $username) ?? '';
        $username = trim($username, '._-');

        return substr($username, 0, 32);
    }

    private function uniqueUsername(string $baseUsername, array $usedUsernames, int $userId): string
    {
        $baseUsername = $this->fitUsernameLength($baseUsername);
        $candidate = $baseUsername;
        $counter = 2;

        while (isset($usedUsernames[$candidate])) {
            $suffix = '_' . $counter;
            $candidate = substr($baseUsername, 0, 32 - strlen($suffix)) . $suffix;
            $counter++;
        }

        if (strlen($candidate) < 3) {
            $candidate = 'user_' . $userId;
        }

        return $candidate;
    }

    private function fitUsernameLength(string $username): string
    {
        if (strlen($username) >= 3) {
            return substr($username, 0, 32);
        }

        return str_pad($username, 3, '_');
    }
}
