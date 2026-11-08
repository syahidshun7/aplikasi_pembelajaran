<?php

use App\Models\Quest;
use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

it('reports real submission and participant counts on the admin redesign dashboard', function () {
    Cache::flush();

    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
        'gold' => 999,
    ]);
    $participant = User::factory()->create(['role' => User::ROLE_USER, 'gold' => 750]);
    $item = ShopItem::query()->create(['name' => 'Tiket ulang', 'code' => 'RETAKE_TEST', 'price_gold' => 250, 'is_active' => true]);
    ShopTransaction::query()->create([
        'user_id' => $participant->id, 'shop_item_id' => $item->id,
        'type' => 'purchase', 'quantity' => 1, 'gold_change' => -250,
    ]);
    ShopTransaction::query()->create([
        'user_id' => $participant->id, 'shop_item_id' => $item->id,
        'type' => 'consume_unlock', 'quantity' => 1, 'gold_change' => 0,
    ]);
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
            ->where('shopOverview.gold_in_circulation', 750)
            ->where('shopOverview.total_transactions', 2)
            ->where('shopOverview.recent_transactions.0.type', 'consume_unlock')
            ->where('shopOverview.recent_transactions.1.item_name', 'Tiket ulang')
            ->etc());
});
