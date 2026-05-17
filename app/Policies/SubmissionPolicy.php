<?php
namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubmissionPolicy
{
    /**
     * Menentukan apakah user bisa melihat detail submission.
     */
    public function view(User $user, Submission $submission): bool
    {
        // 1. Jika dia Admin, izinkan akses ke semua submission
        if ($user->isStaff()) {
            return true;
        }

        // 2. Jika user biasa, cek apakah ID-nya sama dengan pemilik submission
       return (string) $user->id === (string) $submission->user_id;
    }

    /**
     * Menentukan siapa yang boleh mengedit/memberi feedback
     */
    public function update(User $user, Submission $submission): bool
    {
        // Biasanya di industri, hanya admin yang boleh mengedit status tugas
        return $user->isStaff();
    }
}
