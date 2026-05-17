<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationPresenter
{
    public static function summary(User $user, int $limit = 6): array
    {
        return [
            'unread_count' => (int) $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (DatabaseNotification $notification) => self::item($notification))
                ->values()
                ->all(),
        ];
    }

    public static function paginate(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(fn (DatabaseNotification $notification) => self::item($notification))
                ->values()
                ->all(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public static function item(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->id,
            'type' => (string) ($data['type'] ?? class_basename((string) $notification->type)),
            'category' => (string) ($data['category'] ?? 'general'),
            'event' => (string) ($data['event'] ?? 'created'),
            'title' => (string) ($data['title'] ?? 'Notification'),
            'message' => (string) ($data['message'] ?? ''),
            'action_url' => (string) ($data['action_url'] ?? ''),
            'action_label' => (string) ($data['action_label'] ?? 'Buka'),
            'icon' => (string) ($data['icon'] ?? 'fi-rr-bell'),
            'accent' => (string) ($data['accent'] ?? 'cyan'),
            'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
            'resource' => is_array($data['resource'] ?? null) ? $data['resource'] : [],
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
