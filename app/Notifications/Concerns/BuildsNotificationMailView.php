<?php

namespace App\Notifications\Concerns;

trait BuildsNotificationMailView
{
    protected function notificationMailViewData(object $notifiable, array $payload, array $overrides = []): array
    {
        $name = ($notifiable->name ?? $notifiable->username) ?: 'Learner';

        return array_merge([
            'appName' => config('app.name', 'P-QUEST'),
            'userName' => $name,
            'eyebrow' => 'LMS_NOTIFICATION',
            'heading' => strtoupper(str_replace(' ', '_', $payload['title'] ?? 'NOTIFICATION')),
            'intro' => $payload['message'] ?? '',
            'panelBackground' => '#f1f5f9',
            'panelBorder' => '#cbd5e1',
            'panelText' => '#334155',
            'panelBody' => $payload['message'] ?? '',
            'actionUrl' => $payload['action_url'] ?? url('/'),
            'actionLabel' => $payload['action_label'] ?? 'Lihat detail',
            'buttonBackground' => '#10b981',
            'buttonBorder' => '#047857',
            'buttonText' => '#052e2b',
            'footer' => 'Silakan buka aplikasi untuk melihat detail lengkap notifikasi ini.',
        ], $overrides);
    }
}
