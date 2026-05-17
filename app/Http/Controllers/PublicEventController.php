<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicEventController extends Controller
{
    /**
     * Public-facing event detail. Accessible without login so that links
     * can be shared via WhatsApp / social media. Only events that are not
     * tied to a specific study group are considered public.
     */
    public function show(Request $request, string $uuid): Response
    {
        $event = Event::query()
            ->whereNull('study_group_id')
            ->where('uuid', $uuid)
            ->with([
                'job:id,name',
                'images:id,event_id,path,sort_order',
            ])
            ->firstOrFail();

        $payload = [
            'uuid' => (string) $event->uuid,
            'title' => (string) $event->title,
            'description' => (string) ($event->description ?? ''),
            'sequence_order' => (int) ($event->sequence_order ?? 0),
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'self_attendance_enabled' => (bool) $event->self_attendance_enabled,
            'job' => $event->job ? [
                'id' => (int) $event->job->id,
                'name' => (string) $event->job->name,
            ] : null,
            'images' => $event->images->map(fn ($image) => [
                'id' => (int) $image->id,
                'path' => (string) $image->path,
                'url' => (string) $image->url,
                'sort_order' => (int) ($image->sort_order ?? 0),
            ])->values()->all(),
            'guides_count' => (int) $event->guides()->count(),
            'quests_count' => (int) $event->quests()->count(),
        ];

        $shareUrl = route('public.events.show', ['uuid' => $event->uuid]);
        $shareMessage = sprintf('%s - %s', $event->title, $shareUrl);

        return Inertia::render('Events/PublicShow', [
            'event' => $payload,
            'share' => [
                'url' => $shareUrl,
                'message' => $shareMessage,
                'whatsapp_url' => 'https://wa.me/?text=' . rawurlencode($shareMessage),
            ],
            'isAuthenticated' => (bool) $request->user(),
        ]);
    }
}
