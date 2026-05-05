<?php

use App\Models\Creation;
use App\Models\CreationPeerReview;
use App\Models\CreationReview;
use App\Models\CreationReviewPublication;
use App\Models\JobRole;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use App\Models\User;
use App\Notifications\CreationReviewAssignedNotification;
use App\Notifications\CreationReviewPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function makeRubricForCreationReview(User $mentor): array
{
    $rubric = Rubric::query()->create([
        'title' => 'Creation Review Rubric',
        'description' => 'Rubric for mentor preview.',
        'mentor_id' => $mentor->id,
        'max_score' => 100,
    ]);

    $criterionA = RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Problem Clarity',
        'weight' => 50,
        'order' => 1,
    ]);

    $criterionB = RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Execution Quality',
        'weight' => 50,
        'order' => 2,
    ]);

    $levelLow = RubricLevel::query()->create([
        'rubric_id' => $rubric->id,
        'level' => 1,
        'label' => 'Low',
        'score_value' => 1,
    ]);

    $levelHigh = RubricLevel::query()->create([
        'rubric_id' => $rubric->id,
        'level' => 2,
        'label' => 'High',
        'score_value' => 2,
    ]);

    foreach ([$criterionA, $criterionB] as $criterion) {
        RubricDescription::query()->create([
            'criteria_id' => $criterion->id,
            'level_id' => $levelLow->id,
            'description' => 'Needs more work.',
        ]);

        RubricDescription::query()->create([
            'criteria_id' => $criterion->id,
            'level_id' => $levelHigh->id,
            'description' => 'Strong and clear.',
        ]);
    }

    return [
        'rubric' => $rubric,
        'criteria' => [$criterionA, $criterionB],
        'levels' => [
            'low' => $levelLow,
            'high' => $levelHigh,
        ],
    ];
}

function makeCreationForReview(User $creator, array $overrides = []): Creation
{
    $creation = Creation::query()->create([
        'user_id' => $creator->id,
        'title' => 'Review Target Creation',
        'description' => 'Creation description',
        'content' => '<p>Creation content</p>',
        'category' => 'Engineering',
        'status' => 'finished',
        'progress' => 100,
        'is_public' => true,
        'is_open_for_collaboration' => false,
        ...collect($overrides)->except([
            'is_open_for_review',
            'review_status',
            'assigned_reviewer_id',
            'assigned_rubric_id',
        ])->all(),
    ]);

    foreach (['is_open_for_review', 'review_status', 'assigned_reviewer_id', 'assigned_rubric_id'] as $field) {
        if (array_key_exists($field, $overrides)) {
            $creation->{$field} = $overrides[$field];
        }
    }

    $creation->save();

    return $creation;
}

test('admin can assign reviewer and cannot submit mentor review directly', function () {
    Notification::fake();

    $job = JobRole::query()->create([
        'name' => 'Engineering',
        'slug' => 'engineering',
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $creator = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $job->id,
    ]);

    $rubricBundle = makeRubricForCreationReview($mentor);
    $creation = makeCreationForReview($creator, [
        'is_open_for_review' => false,
        'review_status' => 'none',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.creations.assignment.update', ['creation' => $creation->id]), [
            'is_open_for_review' => true,
            'assigned_reviewer_id' => $mentor->id,
            'assigned_rubric_id' => $rubricBundle['rubric']->id,
        ])
        ->assertRedirect();

    $creation->refresh();
    expect((bool) $creation->is_open_for_review)->toBeTrue();
    expect((int) $creation->assigned_reviewer_id)->toBe((int) $mentor->id);
    expect((int) $creation->assigned_rubric_id)->toBe((int) $rubricBundle['rubric']->id);
    Notification::assertSentTo($mentor, CreationReviewAssignedNotification::class);

    $criteria = $rubricBundle['criteria'];
    $highLevelId = (int) $rubricBundle['levels']['high']->id;

    $this->actingAs($admin)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'approved',
            'feedback' => 'Great implementation.',
            'selected_levels' => [
                $criteria[0]->id => $highLevelId,
                $criteria[1]->id => $highLevelId,
            ],
        ])
        ->assertForbidden();

    expect(CreationPeerReview::query()->where('creation_id', $creation->id)->exists())->toBeFalse();
    expect(CreationReview::query()->where('creation_id', $creation->id)->exists())->toBeFalse();
});

test('mentor with matching job can preview and submit review', function () {
    $job = JobRole::query()->create([
        'name' => 'Design',
        'slug' => 'design',
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $creator = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $job->id,
    ]);

    $rubricBundle = makeRubricForCreationReview($mentor);
    $creation = makeCreationForReview($creator, [
        'is_open_for_review' => true,
        'review_status' => 'pending',
        'assigned_rubric_id' => $rubricBundle['rubric']->id,
    ]);

    $criteria = $rubricBundle['criteria'];
    $highLevelId = (int) $rubricBundle['levels']['high']->id;

    $this->actingAs($mentor)
        ->get(route('admin.creations.preview', ['creation' => $creation->id]))
        ->assertOk();

    $this->actingAs($mentor)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'approved',
            'feedback' => 'Solid progress.',
            'selected_levels' => [
                $criteria[0]->id => $highLevelId,
                $criteria[1]->id => $highLevelId,
            ],
        ])
        ->assertRedirect();

    $peerReview = CreationPeerReview::query()
        ->where('creation_id', $creation->id)
        ->where('reviewer_id', $mentor->id)
        ->first();

    expect($peerReview)->not()->toBeNull();
    expect((int) $peerReview->score_percent)->toBe(100);
    expect(is_array($peerReview->rubric_snapshot))->toBeTrue();
    expect((string) ($peerReview->rubric_snapshot['rubric']['title'] ?? ''))->toBe('Creation Review Rubric');
    expect(CreationReview::query()->where('creation_id', $creation->id)->exists())->toBeFalse();
});

test('mentor outside job scope is blocked unless explicitly assigned', function () {
    $jobA = JobRole::query()->create([
        'name' => 'Product',
        'slug' => 'product',
    ]);

    $jobB = JobRole::query()->create([
        'name' => 'Marketing',
        'slug' => 'marketing',
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $jobA->id,
    ]);

    $rubricOwner = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $jobB->id,
    ]);

    $creator = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $jobB->id,
    ]);

    $rubricBundle = makeRubricForCreationReview($rubricOwner);
    $creation = makeCreationForReview($creator, [
        'is_open_for_review' => true,
        'review_status' => 'pending',
        'assigned_rubric_id' => $rubricBundle['rubric']->id,
    ]);

    $this->actingAs($mentor)
        ->get(route('admin.creations.preview', ['creation' => $creation->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.creations.assignment.update', ['creation' => $creation->id]), [
            'assigned_reviewer_id' => $mentor->id,
        ])
        ->assertRedirect();

    $this->actingAs($mentor)
        ->get(route('admin.creations.preview', ['creation' => $creation->id]))
        ->assertOk();
});

test('mentor review queue shows same-job or assigned creations only', function () {
    $jobA = JobRole::query()->create([
        'name' => 'Data',
        'slug' => 'data',
    ]);

    $jobB = JobRole::query()->create([
        'name' => 'Research',
        'slug' => 'research',
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $jobA->id,
    ]);

    $creatorSameJob = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $jobA->id,
    ]);

    $creatorOtherJob = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $jobB->id,
    ]);

    $visibleByJob = makeCreationForReview($creatorSameJob, [
        'title' => 'Visible By Job',
        'is_open_for_review' => true,
        'review_status' => 'pending',
    ]);

    $visibleByAssignment = makeCreationForReview($creatorOtherJob, [
        'title' => 'Visible By Assignment',
        'is_open_for_review' => true,
        'review_status' => 'pending',
        'assigned_reviewer_id' => $mentor->id,
    ]);

    $hiddenCreation = makeCreationForReview($creatorOtherJob, [
        'title' => 'Hidden Creation',
        'is_open_for_review' => true,
        'review_status' => 'pending',
    ]);

    $response = $this->actingAs($mentor)
        ->get(route('admin.creations.queue'));

    $response->assertOk();
    $response->assertSee('Visible By Job');
    $response->assertSee('Visible By Assignment');
    $response->assertDontSee('Hidden Creation');
});

test('admin can publish official result from selected peer review', function () {
    Notification::fake();

    $job = JobRole::query()->create([
        'name' => 'Backend',
        'slug' => 'backend',
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $mentorA = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $mentorB = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $creator = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $job->id,
    ]);

    $rubricBundle = makeRubricForCreationReview($mentorA);
    $creation = makeCreationForReview($creator, [
        'is_open_for_review' => true,
        'review_status' => 'pending',
        'assigned_rubric_id' => $rubricBundle['rubric']->id,
    ]);

    $criteria = $rubricBundle['criteria'];
    $highLevelId = (int) $rubricBundle['levels']['high']->id;
    $lowLevelId = (int) $rubricBundle['levels']['low']->id;

    $this->actingAs($mentorA)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'approved',
            'feedback' => 'Mentor A approved.',
            'selected_levels' => [
                $criteria[0]->id => $highLevelId,
                $criteria[1]->id => $highLevelId,
            ],
        ])
        ->assertRedirect();

    $this->actingAs($mentorB)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'needs_revision',
            'feedback' => 'Mentor B requested revision.',
            'selected_levels' => [
                $criteria[0]->id => $lowLevelId,
                $criteria[1]->id => $highLevelId,
            ],
        ])
        ->assertRedirect();

    $peerFromMentorB = CreationPeerReview::query()
        ->where('creation_id', $creation->id)
        ->where('reviewer_id', $mentorB->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.creations.review.publish', [
            'creation' => $creation->id,
            'peerReview' => $peerFromMentorB->id,
        ]))
        ->assertRedirect();

    $official = CreationReview::query()->where('creation_id', $creation->id)->first();
    expect($official)->not()->toBeNull();
    expect((int) $official->reviewer_id)->toBe((int) $mentorB->id);
    expect($official->status)->toBe('needs_revision');
    expect((int) ($official->source_peer_review_id ?? 0))->toBe((int) $peerFromMentorB->id);
    expect(is_array($official->rubric_snapshot))->toBeTrue();
    expect((int) CreationReviewPublication::query()->where('creation_id', $creation->id)->count())->toBe(1);
    Notification::assertSentTo($creator, CreationReviewPublishedNotification::class);
});

test('admin can publish official aggregate result from all available mentor reviews', function () {
    Notification::fake();

    $job = JobRole::query()->create([
        'name' => 'Frontend',
        'slug' => 'frontend',
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $mentorA = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $mentorB = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);
    $mentorC = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $creator = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $job->id,
    ]);

    $rubricBundle = makeRubricForCreationReview($mentorA);
    $creation = makeCreationForReview($creator, [
        'is_open_for_review' => true,
        'review_status' => 'pending',
        'assigned_rubric_id' => $rubricBundle['rubric']->id,
    ]);

    $criteria = $rubricBundle['criteria'];
    $highLevelId = (int) $rubricBundle['levels']['high']->id;
    $lowLevelId = (int) $rubricBundle['levels']['low']->id;

    $this->actingAs($mentorA)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'approved',
            'feedback' => 'Mentor A approved.',
            'selected_levels' => [
                $criteria[0]->id => $highLevelId,
                $criteria[1]->id => $highLevelId,
            ],
        ])
        ->assertRedirect();

    $this->actingAs($mentorB)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'needs_revision',
            'feedback' => 'Mentor B needs revision.',
            'selected_levels' => [
                $criteria[0]->id => $lowLevelId,
                $criteria[1]->id => $highLevelId,
            ],
        ])
        ->assertRedirect();

    $this->actingAs($mentorC)
        ->post(route('admin.creations.review.submit', ['creation' => $creation->id]), [
            'status' => 'approved',
            'feedback' => 'Mentor C approved.',
            'selected_levels' => [
                $criteria[0]->id => $lowLevelId,
                $criteria[1]->id => $lowLevelId,
            ],
        ])
        ->assertRedirect();

    $peerReviews = CreationPeerReview::query()
        ->where('creation_id', $creation->id)
        ->orderBy('id')
        ->get();

    expect($peerReviews->count())->toBe(3);

    $this->actingAs($admin)
        ->post(route('admin.creations.review.publish-aggregate', [
            'creation' => $creation->id,
        ]))
        ->assertRedirect();

    $official = CreationReview::query()->where('creation_id', $creation->id)->first();
    expect($official)->not()->toBeNull();
    expect((int) $official->reviewer_id)->toBe((int) $admin->id);
    expect((int) $official->score_percent)->toBe(75);
    expect((string) $official->status)->toBe('needs_revision');
    expect($official->source_peer_review_id)->toBeNull();
    expect((string) data_get($official->result_breakdown, 'mode'))->toBe('aggregate_reviews');
    expect(count((array) data_get($official->result_breakdown, 'peer_review_ids', [])))->toBe(3);

    $publication = CreationReviewPublication::query()
        ->where('creation_id', $creation->id)
        ->latest('id')
        ->first();

    expect($publication)->not()->toBeNull();
    expect($publication->peer_review_id)->toBeNull();
    expect((string) data_get($publication->payload, 'mode'))->toBe('aggregate_reviews');
    expect(count((array) data_get($publication->payload, 'peer_review_ids', [])))->toBe(3);

    Notification::assertSentTo($creator, CreationReviewPublishedNotification::class);
});
