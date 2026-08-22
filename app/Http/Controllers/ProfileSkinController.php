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
            'backUrl' => $this->resolvePreviewBackUrl($request),
        ]);
    }

    private function resolvePreviewBackUrl(Request $request): string
    {
        $candidate = (string) $request->query('back', '');
        if ($this->isSafePreviewBackUrl($candidate)) {
            return $candidate;
        }

        $previous = url()->previous();
        if ($this->isSafePreviewBackUrl($previous) && $previous !== url()->current()) {
            return $previous;
        }

        return route('shop.index');
    }

    private function isSafePreviewBackUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== request()->getHost()) {
            return false;
        }

        return is_string($path)
            && ! preg_match('#^/profile/skins/\d+/preview$#', $path);
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
