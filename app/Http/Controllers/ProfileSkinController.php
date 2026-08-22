<?php

namespace App\Http\Controllers;

use App\Models\ProfileSkin;
use App\Support\ProfileSkinPreviewPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileSkinController extends Controller
{
    public function preview(Request $request, ProfileSkin $skin): Response
    {
        if (! $skin->is_active) {
            abort(404);
        }

        return Inertia::render('ProfileSkins/Preview', [
            'skin' => $skin->toThemeArray(),
            'previewPayload' => ProfileSkinPreviewPayload::make($request->user()),
            'backUrl' => url()->previous() !== url()->current() ? url()->previous() : route('shop.index'),
        ]);
    }

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
