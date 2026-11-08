<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

it('reports real submission and participant counts on the admin redesign dashboard', function () {
    Cache::flush();

    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);
    $participant = User::factory()->create(['role' => User::ROLE_USER]);
    $quest = Quest::query()->create([
        'title' => 'Dashboard insight test',
        'difficulty' => 'C-Rank',
        'reward_gold' => 100,
        'reward_exp' => 100,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    foreach ([['Approved', 84], ['Pending', 0]] as $index => [$status, $grade]) {
        Submission::query()->create([
            'quest_id' => $quest->id,
            'user_id' => $participant->id,
            'content' => 'Jawaban untuk grafik dashboard',
            'status' => $status,
            'grade' => $grade,
            'attempt_number' => $index + 1,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('redesign.admin-dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboardInsights.submitted_14d', 2)
            ->where('dashboardInsights.reviewed_14d', 1)
            ->where('dashboardInsights.new_participants_14d', 1)
            ->where('dashboardInsights.statuses.pending', 1)
            ->where('dashboardInsights.statuses.approved', 1)
            ->where('stats.avg_grade_30d', 84)
            ->where('overviewStudents.0.id', $participant->id)
            ->etc());
});
