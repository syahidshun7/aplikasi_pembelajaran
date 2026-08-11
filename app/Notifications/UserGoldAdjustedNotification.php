<?php

namespace App\Notifications;

use App\Models\UserGoldAdjustment;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserGoldAdjustedNotification extends Notification
{
    use BuildsNotificationPayload;

    public function __construct(
        private readonly UserGoldAdjustment $adjustment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        $adjustment = $this->adjustment->loadMissing('admin:id,name,username');
        $goldChange = (int) ($adjustment->gold_change ?? 0);
        $amount = abs($goldChange);
        $isAdded = $goldChange > 0;
        $adminName = (string) ($adjustment->admin?->username ?: $adjustment->admin?->name ?: 'Admin');

        return $this->buildPayload([
            'type' => 'gold',
            'category' => 'economy',
            'event' => $isAdded ? 'gold_added' : 'gold_subtracted',
            'title' => $isAdded ? 'Gold Ditambahkan' : 'Gold Dikurangi',
            'message' => sprintf(
                '%s %s %d gold. Saldo sekarang: %d gold.',
                $adminName,
                $isAdded ? 'menambahkan' : 'mengurangi',
                $amount,
                (int) ($adjustment->gold_after ?? 0),
            ),
            'action_url' => route('shop.index'),
            'action_label' => 'Lihat shop',
            'icon' => 'fi-rr-coins',
            'accent' => $isAdded ? 'yellow' : 'red',
            'resource' => [
                'type' => 'user_gold_adjustment',
                'id' => (int) $adjustment->id,
            ],
            'meta' => [
                'gold_before' => (int) ($adjustment->gold_before ?? 0),
                'gold_after' => (int) ($adjustment->gold_after ?? 0),
                'gold_change' => $goldChange,
                'reason' => (string) ($adjustment->reason ?? ''),
                'admin_user_id' => (int) ($adjustment->admin_user_id ?? 0),
                'admin_username' => $adminName,
            ],
        ]);
    }
}
