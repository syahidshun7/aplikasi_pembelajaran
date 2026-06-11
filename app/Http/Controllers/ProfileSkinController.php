<?php

namespace App\Http\Controllers;

use App\Models\ProfileSkin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileSkinController extends Controller
{
    /**
     * Activate a skin the user owns.
     */
    public function activate(Request $request, ProfileSkin $skin): RedirectResponse
    {
        $user = $request->user();

        if (! $skin->is_active) {
            abort(404);
        }

        if ((bool) $user->isStaffPlayMode()) {
            throw ValidationException::withMessages([
                'skin' => 'Staff play mode tidak bisa mengubah skin profil.',
            ]);
        }

        if (! $skin->isOwnedBy($user)) {
            throw ValidationException::withMessages([
                'skin' => 'Kamu belum memiliki skin ini. Beli di shop terlebih dahulu.',
            ]);
        }

        $user->update(['active_profile_skin_id' => $skin->id]);

        return back()->with('message', 'PROFILE_SKIN_ACTIVATED');
    }

    /**
     * Remove active skin (reset to default).
     */
    public function deactivate(Request $request): RedirectResponse
    {
        $request->user()->update(['active_profile_skin_id' => null]);

        return back()->with('message', 'PROFILE_SKIN_DEACTIVATED');
    }
}
