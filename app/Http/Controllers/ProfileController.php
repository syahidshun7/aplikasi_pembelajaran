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

    // 1. Ambil semua submission yang sudah diproses (sudah ada nilainya)
    $completedSubmissions = \App\Models\Submission::where('user_id', $user->id)
        ->whereIn('status', ['Approved', 'Rejected']) // Hanya hitung yang sudah dinilai
        ->get();

    $totalCompleted = $completedSubmissions->count();

    // 2. Hitung rata-rata Grade
    // Kita gunakan collection sum() dan dibagi totalnya
    $averageGrade = $totalCompleted > 0 
        ? round($completedSubmissions->sum('grade') / $totalCompleted, 1) 
        : 0;

    $userData = [
        'id'    => $user->id,
        'uuid'  => $user->uuid,
        'name'  => $user->name,
        'email' => $user->email,
        'gold'  => $user->gold ?? 0,
        'lvl'   => $user->lvl ?? 1,
        'exp'   => $user->exp ?? 0, // [TAMBAHAN] Masukkan EXP agar bar di frontend jalan
        'role'  => $user->role,
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
                'grade'        => $submission->grade, // [OPSIONAL] Jika ingin tampilkan grade per baris
                'submitted_at' => $submission->created_at->diffForHumans(),
                'quest_uuid'   => $submission->quest?->uuid 
            ];
        });

    return Inertia::render('Profile/Edit', [
        'user'            => $userData,
        'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
        'status'          => session('status'),
        'userQuests'      => $userQuests,
        'averageGrade'    => $averageGrade,    // [DIKIRIM KE FRONTEND]
        'totalCompleted'  => $totalCompleted,  // [DIKIRIM KE FRONTEND]
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
