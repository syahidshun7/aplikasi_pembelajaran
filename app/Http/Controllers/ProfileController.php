<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Models\Submission;
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

    // Mengambil riwayat pengiriman tugas user
    // Kita "join" dengan data Quest-nya agar bisa menampilkan Judul Quest
    $userQuests = \App\Models\Submission::where('user_id', $user->id)
        ->with('quest') // Memanggil relasi 'quest' di model Submission
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($submission) {
            return [
                'id' => $submission->id,
                'title' => $submission->quest->title, // Ambil judul dari tabel quests
                'status' => $submission->status,      // Status: 'pending', 'approved', 'rejected'
                'submitted_at' => $submission->created_at->diffForHumans(),
                'quest_id' => $submission->quest_id
            ];
        });

    return Inertia::render('Profile/Edit', [
        'mustVerifyEmail' => $user instanceof MustVerifyEmail,
        'status' => session('status'),
        'userQuests' => $userQuests, 
    ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
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
