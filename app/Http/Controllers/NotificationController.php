<?php

namespace App\Http\Controllers;

use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()->notifications();
        if ($request->routeIs('redesign.notifications')) {
            $filter = $request->validate(['filter' => ['nullable', 'in:all,unread,quest,class,system']])['filter'] ?? 'all';
            if ($filter === 'unread') $query->whereNull('read_at');
            if (in_array($filter, ['quest', 'class', 'system'], true)) $query->where('data->category', $filter);
        }
        $notifications = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render($request->routeIs('redesign.notifications') ? 'Redesign/AdminDashboard/Notifications' : 'Notifications/Index', [
            'notifications' => NotificationPresenter::paginate($notifications),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'filter' => $filter ?? 'all',
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json([
            'summary' => NotificationPresenter::summary($request->user()),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse|RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $summary = NotificationPresenter::summary($request->user());

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['summary' => $summary]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        $summary = NotificationPresenter::summary($request->user());

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['summary' => $summary]);
        }

        return back();
    }
}
