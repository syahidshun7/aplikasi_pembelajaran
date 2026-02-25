<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'username',
                'email',
                'role',
                'gold',
                'exp',
                'level',
                'created_at',
            ]);

        return Inertia::render('Users/Admin/Index', [
            'users' => $users,
            'availableRoles' => ['admin', 'user', 'student'],
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,user,student'],
        ]);

        // Prevent an admin from accidentally removing their own admin access.
        if ((int) $request->user()->id === (int) $user->id && $validated['role'] !== 'admin') {
            return back()->withErrors([
                'role' => 'Kamu tidak bisa menurunkan role akun admin yang sedang login.',
            ]);
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return back()->with('message', 'USER_ROLE_UPDATED');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return back()->with('message', 'USER_PASSWORD_RESET_SUCCESS');
    }
}
