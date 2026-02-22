<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class AdminStudyGroupController extends Controller
{
     public function manage()
{
    // Pastikan hanya admin boleh masuk
    if (auth()->user()->role !== 'admin') {
        abort(403, 'Hanya Admin (Grandmaster) yang dibenarkan masuk ke Command Center!');
    }

    return Inertia::render('StudyGroups/Admin/Index', [
        'groups' => StudyGroup::withCount('users')->latest()->get(),
    ]);
}
     
    public function store(Request $request)
    {
        // Pastikan hanya admin (sesuaikan dengan logic middleware/role kamu)
        if (Auth::user()->role !== 'admin') {
            abort(403, 'UNAUTHORIZED_ACCESS');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'required|integer|min:1|max:50',
        ]);

        // Karena Model StudyGroup sudah pakai HasUuids, 
        // kita tidak perlu menulis 'uuid' => Str::uuid() di sini.
        StudyGroup::create([
            'name' => $request->name,
            'description' => $request->description,
            'max_members' => $request->max_members,
            'invite_code' => 'GRP-' . strtoupper(Str::random(6)),
        ]);

        return back()->with('message', 'NEW_PARTY_ESTABLISHED');
    }


    public function update(Request $request, $uuid)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'required|integer|min:1|max:50',
        ]);

        $group->update([
            'name' => $request->name,
            'description' => $request->description,
            'max_members' => $request->max_members,
        ]);

        return back()->with('message', 'DATA_UPDATED_SUCCESSFULLY');
    }

    /**
     * ADMIN ACTION: Disband/Delete a study group.
     */
    public function destroy($uuid)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        // Pivot table otomatis terhapus jika di migration kamu pakai onDelete('cascade')
        $group->delete();

        return back()->with('message', 'PARTY_DISBANDED');
    }

   
}
