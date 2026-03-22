<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Services\LmsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;

class NotificationDispatchController extends Controller
{
    public function chat(Request $request, LmsNotificationService $notifications): JsonResponse
    {
        $sender = $request->user();
        if (! $sender) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! RateLimiter::attempt(
            "notifications:chat-dispatch:{$sender->id}",
            30,
            static fn () => null,
            60
        )) {
            return response()->json([
                'message' => 'Terlalu banyak notifikasi chat dalam waktu singkat. Coba lagi sebentar.',
            ], 429);
        }

        $validated = $request->validate([
            'recipient_ids' => ['required', 'array', 'min:1', 'max:20'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'message' => ['nullable', 'string', 'max:1000'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);

        $recipientIds = collect($validated['recipient_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $sender->id)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return response()->json([
                'message' => 'Recipient tidak valid atau sama dengan akun pengirim.',
            ], 422);
        }

        $recipients = $this->resolveChatRecipients($sender, $recipientIds);
        if ($recipients->count() !== $recipientIds->count()) {
            return response()->json([
                'message' => 'Sebagian recipient tidak diizinkan untuk menerima notifikasi chat ini.',
            ], 422);
        }

        $message = ! empty($validated['message_id'])
            ? Message::query()->find($validated['message_id'])
            : null;

        $notifications->notifyChatMessage(
            $recipients,
            $sender,
            $message,
            $validated['message'] ?? null,
            $validated['room'] ?? null,
        );

        return response()->json(['status' => 'ok']);
    }

    public function announcement(Request $request, LmsNotificationService $notifications): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'action_url' => ['nullable', 'string', 'max:2048'],
            'action_label' => ['nullable', 'string', 'max:100'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $recipients = ! empty($validated['recipient_ids'])
            ? User::query()->whereIn('id', $validated['recipient_ids'])->get()
            : User::query()->get();

        $notifications->notifyAnnouncement($recipients, [
            'id' => (string) now()->timestamp,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'action_url' => $validated['action_url'] ?? route('notifications.index'),
            'action_label' => $validated['action_label'] ?? 'Buka pengumuman',
            'meta' => [
                'created_by' => (int) $request->user()->id,
            ],
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function resolveChatRecipients(User $sender, Collection $recipientIds): Collection
    {
        $query = User::query()
            ->whereIn('id', $recipientIds->all())
            ->where('id', '!=', (int) $sender->id);

        if (! $sender->isStaff()) {
            $groupIds = $sender->studyGroups()
                ->pluck('study_groups.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($groupIds)) {
                return collect();
            }

            $query->whereNotIn('role', User::staffRoles())
                ->whereHas('studyGroups', function ($groupQuery) use ($groupIds) {
                    $groupQuery->whereIn('study_groups.id', $groupIds);
                });
        }

        return $query->get();
    }
}
