<?php

namespace App\Http\Middleware;

use App\Models\StudyGroup;
use App\Services\StudyGroupStaffAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudyGroupStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $group = $request->route('group');

        if (! $group instanceof StudyGroup) {
            $group = StudyGroup::query()
                ->where('uuid', (string) ($request->route('group') ?? $request->route('uuid') ?? ''))
                ->firstOrFail();
        }

        if (! app(StudyGroupStaffAccessService::class)->canAccess($request->user(), $group)) {
            abort(403, 'STUDY_GROUP_STAFF_ACCESS_DENIED');
        }

        return $next($request);
    }
}
