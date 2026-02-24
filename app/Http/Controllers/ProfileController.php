<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // 1. Ambil semua submission yang sudah diproses (Logic Asli Kamu)
        $completedSubmissions = \App\Models\Submission::where('user_id', $user->id)
            ->whereIn('status', ['Approved', 'Rejected'])
            ->get();

        $totalCompleted = $completedSubmissions->count();

        // 2. Hitung rata-rata Grade (Logic Asli Kamu)
        $averageGrade = $totalCompleted > 0
            ? round($completedSubmissions->sum('grade') / $totalCompleted, 1)
            : 0;

        $userData = [
            'id'            => $user->id,
            'uuid'          => $user->uuid,
            'name'          => $user->name,
            'username'      => $user->username,      // [TAMBAHAN] Kirim Username
            'profile_photo' => $user->profile_photo, // [TAMBAHAN] Kirim Path Foto
            'email'         => $user->email,
            'gold'          => $user->gold ?? 0,
            'lvl'           => $user->lvl ?? 1,
            'exp'           => $user->exp ?? 0,
            'role'          => $user->role,
        ];

        $userQuests = \App\Models\Submission::where('user_id', $user->id)
            ->with('quest')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($submission) {
                return [
                    'id'           => $submission->id,
                    'uuid'         => $submission->uuid,
                    'title'        => $submission->quest?->title ?? 'Unknown Quest',
                    'status'       => $submission->status,
                    'grade'        => $submission->grade,
                    'submitted_at' => $submission->created_at->diffForHumans(),
                    'quest_uuid'   => $submission->quest?->uuid
                ];
            });

        return Inertia::render('Profile/Edit', [
            'user'            => $userData,
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            'status'          => session('status'),
            'userQuests'      => $userQuests,
            'averageGrade'    => $averageGrade,
            'totalCompleted'  => $totalCompleted,
        ]);
    }
    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // 1. Isi data teks (name, email, username) dari hasil validasi
        $user->fill($request->validated());

        // 2. Reset verifikasi email jika email diubah
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // 3. Handle Update Foto Profil (Artifact Update)
        if ($request->hasFile('profile_photo')) {
            // Hapus foto lama dari storage agar tidak menumpuk (sampah data)
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Simpan foto baru ke folder 'profiles' di disk 'public'
            $path = $request->file('profile_photo')->store('profiles', 'public');

            // Simpan path-nya ke kolom profile_photo di database
            $user->profile_photo = $path;
        }

        // 4. Eksekusi simpan ke database
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
