<?php

namespace App\Notifications\Concerns;

trait BuildsNotificationPayload
{
    protected function buildPayload(array $payload): array
    {
        return [
            'type' => (string) ($payload['type'] ?? 'general'),
            'category' => (string) ($payload['category'] ?? 'general'),
            'event' => (string) ($payload['event'] ?? 'created'),
            'title' => (string) ($payload['title'] ?? 'Notification'),
            'message' => (string) ($payload['message'] ?? ''),
            'action_url' => (string) ($payload['action_url'] ?? ''),
            'action_label' => (string) ($payload['action_label'] ?? 'Buka'),
            'icon' => (string) ($payload['icon'] ?? 'fi-rr-bell'),
            'accent' => (string) ($payload['accent'] ?? 'cyan'),
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            'resource' => is_array($payload['resource'] ?? null) ? $payload['resource'] : [],
        ];
    }
}
