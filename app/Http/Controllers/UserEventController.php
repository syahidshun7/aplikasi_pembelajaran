<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserEventController extends Controller
{
    public function show(Event $event): Response
    {
        $user = Auth::user();
        $userGroupIds = $user->studyGroups()->pluck('study_groups.id')->toArray();

        $isAccessible = is_null($event->study_group_id) || in_array((int) $event->study_group_id, $userGroupIds, true);
        abort_unless($isAccessible, 403, 'EVENT_ACCESS_DENIED');

        $event->load([
            'studyGroup:id,name',
            'guides' => function ($q) use ($event) {
                $q->select('guides.id', 'guides.uuid', 'guides.title', 'guides.description', 'guides.file_path', 'guides.study_group_id')
                    ->with('studyGroup:id,name');
            },
            'quests' => function ($q) use ($event) {
                $q->select('quests.id', 'quests.uuid', 'quests.title', 'quests.description', 'quests.difficulty', 'quests.status', 'quests.deadline', 'quests.reward_gold', 'quests.reward_exp', 'quests.study_group_id')
                    ->with('studyGroup:id,name');
            },
        ]);

        return Inertia::render('Events/UserShow', [
            'event' => $event,
        ]);
    }
}

