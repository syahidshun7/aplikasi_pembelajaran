<?php
namespace App\Http\Controllers;

use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class StudyGroupController extends Controller
{
    // Lihat daftar semua party
    public function index()
    {
         $userId = Auth::id();

        return Inertia::render('StudyGroups/Index', [
            'groups' => StudyGroup::withCount('users')->latest()->get(),

            // Mengambil grup milik user melalui query langsung ke Model StudyGroup
            // Ini jauh lebih aman dari error "undefined method"
            'myGroups' => Auth::check()
                ? StudyGroup::whereHas('users', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->withCount('users')->get()
                : []
        ]);
    }

    // Logic JOIN Party
    public function join(Request $request)
    {
        $request->validate(['invite_code' => 'required|string']);

        $group = StudyGroup::where('invite_code', $request->invite_code)->first();

        if (!$group) {
            return back()->withErrors(['invite_code' => 'KODE_INVALID: Party tidak ditemukan.']);
        }

        if ($group->users()->where('user_id', Auth::id())->exists()) {
            return back()->withErrors(['invite_code' => 'ALREADY_MEMBER: Kamu sudah di dalam party ini.']);
        }

        if ($group->users()->count() >= $group->max_members) {
            return back()->withErrors(['invite_code' => 'PARTY_FULL: Kapasitas party sudah maksimal.']);
        }

        $group->users()->attach(Auth::id());
        return back()->with('message', 'JOINED_SUCCESSFULLY');
    }

    // Logic LEAVE Party
    public function leave($uuid)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $group->users()->detach(Auth::id());

        return back()->with('message', 'LEFT_THE_PARTY');
    }
}