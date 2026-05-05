<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\CreationPeerReview;
use App\Models\CreationReview;
use App\Models\CreationReviewPublication;
use App\Models\Rubric;
use App\Models\RubricDescription;
use App\Models\User;
use App\Notifications\CreationReviewAssignedNotification;
use App\Notifications\CreationReviewPublishedNotification;
use App\Services\RubricScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminCreationReviewController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum melakukan review creation.';

    public function index(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'review_status' => ['nullable', 'in:all,pending,needs_revision,approved,none'],
            'scope' => ['nullable', 'in:all,assigned,job'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $reviewStatus = (string) ($validated['review_status'] ?? 'pending');
        $scope = (string) ($validated['scope'] ?? 'all');

        $query = Creation::query()
            ->with([
                'user:id,name,username,role,job_id',
                'assignedReviewer:id,name,username,job_id',
                'assignedRubric:id,title',
                'finalReview.reviewer:id,name,username',
                'finalReview.rubric:id,title',
            ])
            ->withCount('peerReviews')
            ->where(function ($builder) {
                $builder->where('is_open_for_review', true)
                    ->orWhereIn('review_status', ['pending', 'needs_revision', 'approved'])
                    ->orWhereNotNull('assigned_reviewer_id')
                    ->orWhereNotNull('assigned_rubric_id');
            })
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($reviewStatus !== 'all', fn ($builder) => $builder->where('review_status', $reviewStatus));

        if ($this->isAdminUser($user)) {
            if ($scope === 'assigned') {
                $query->where('assigned_reviewer_id', (int) $user->id);
            }
        } else {
            $mentorJobId = $this->requireMentorJobId($user);
            $query->where(function ($builder) use ($user, $mentorJobId, $scope) {
                if ($scope !== 'job') {
                    $builder->where('assigned_reviewer_id', (int) $user->id);
                }

                if ($scope !== 'assigned') {
                    $builder->orWhereHas('user', fn ($creatorQuery) => $creatorQuery->where('job_id', $mentorJobId));
                }
            });
        }

        $creations = $query
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Creation $creation) => [
                'id' => (int) $creation->id,
                'title' => (string) $creation->title,
                'category' => (string) ($creation->category ?? ''),
                'status' => (string) ($creation->status ?? 'crafting'),
                'progress' => (int) ($creation->progress ?? 0),
                'review_status' => (string) ($creation->review_status ?? 'none'),
                'is_open_for_review' => (bool) ($creation->is_open_for_review ?? false),
                'assigned_reviewer_id' => $creation->assigned_reviewer_id ? (int) $creation->assigned_reviewer_id : null,
                'assigned_rubric_id' => $creation->assigned_rubric_id ? (int) $creation->assigned_rubric_id : null,
                'updated_at' => $creation->updated_at?->toISOString(),
                'peer_reviews_count' => (int) ($creation->peer_reviews_count ?? 0),
                'creator' => [
                    'id' => (int) ($creation->user?->id ?? 0),
                    'name' => (string) ($creation->user?->name ?? ''),
                    'username' => (string) ($creation->user?->username ?? ''),
                    'job_id' => $creation->user?->job_id ? (int) $creation->user->job_id : null,
                ],
                'assigned_reviewer' => $creation->assignedReviewer ? [
                    'id' => (int) $creation->assignedReviewer->id,
                    'name' => (string) $creation->assignedReviewer->name,
                    'username' => (string) ($creation->assignedReviewer->username ?? ''),
                    'job_id' => $creation->assignedReviewer->job_id ? (int) $creation->assignedReviewer->job_id : null,
                ] : null,
                'assigned_rubric' => $creation->assignedRubric ? [
                    'id' => (int) $creation->assignedRubric->id,
                    'title' => (string) $creation->assignedRubric->title,
                ] : null,
                'final_review' => $creation->finalReview ? [
                    'id' => (int) $creation->finalReview->id,
                    'score_percent' => (int) $creation->finalReview->score_percent,
                    'status' => (string) $creation->finalReview->status,
                    'reviewer_name' => (string) ($creation->finalReview->reviewer?->name ?? ''),
                    'rubric_title' => (string) ($creation->finalReview->rubric?->title ?? ''),
                    'reviewed_at' => $creation->finalReview->reviewed_at?->toISOString(),
                ] : null,
            ]);

        return Inertia::render('Creations/Admin/Index', [
            'creations' => $creations,
            'filters' => [
                'search' => $search,
                'review_status' => $reviewStatus,
                'scope' => $scope,
            ],
            'isAdmin' => $this->isAdminUser($user),
            'isMentor' => $user->isMentor(),
        ]);
    }

    public function preview(Request $request, Creation $creation): Response
    {
        $user = $request->user();
        $this->assertCanPreviewCreation($creation, $user);

        $creation->load([
            'user:id,name,username,role,job_id,profile_photo',
            'photos:id,creation_id,path,sort_order',
            'assignedReviewer:id,name,username,role,job_id',
            'assignedRubric:id,title,mentor_id,deleted_at',
            'finalReview.reviewer:id,name,username',
            'finalReview.rubric:id,title',
            'finalReview.publisher:id,name,username',
            'peerReviews.reviewer:id,name,username',
            'peerReviews.rubric:id,title',
            'reviewPublications.publisher:id,name,username',
            'reviewPublications.peerReview.reviewer:id,name,username',
        ]);

        $assignedRubric = $creation->assigned_rubric_id
            ? Rubric::withTrashed()->find((int) $creation->assigned_rubric_id)
            : null;

        $rubricPayload = $assignedRubric ? $this->buildRubricEvaluationPayload($assignedRubric) : null;
        $officialReview = $creation->finalReview;
        $myReview = $creation->peerReviews
            ->firstWhere('reviewer_id', (int) $user->id);

        $isAdmin = $this->isAdminUser($user);

        return Inertia::render('Creations/Admin/Preview', [
            'creation' => $this->serializeCreation($creation),
            'finalReview' => $officialReview ? $this->serializeReview($officialReview) : null,
            'peerReviews' => $creation->peerReviews
                ->map(fn (CreationPeerReview $peerReview) => $this->serializePeerReview($peerReview))
                ->values()
                ->all(),
            'publicationLogs' => $creation->reviewPublications
                ->map(fn (CreationReviewPublication $log) => $this->serializePublicationLog($log))
                ->values()
                ->all(),
            'myReview' => $myReview ? $this->serializePeerReview($myReview) : null,
            'rubric' => $rubricPayload,
            'permissions' => [
                'can_assign' => $isAdmin,
                'can_review' => $user->isMentor(),
                'can_publish_official' => $isAdmin,
                'can_publish_aggregate' => $isAdmin,
            ],
            'assignmentOptions' => [
                'reviewers' => $isAdmin
                    ? User::query()
                        ->where('role', User::ROLE_MENTOR)
                        ->orderBy('name')
                        ->get(['id', 'name', 'username', 'job_id'])
                        ->map(fn (User $mentor) => [
                            'id' => (int) $mentor->id,
                            'name' => (string) $mentor->name,
                            'username' => (string) ($mentor->username ?? ''),
                            'job_id' => $mentor->job_id ? (int) $mentor->job_id : null,
                        ])
                        ->values()
                        ->all()
                    : [],
                'rubrics' => $isAdmin
                    ? Rubric::withTrashed()
                        ->with('mentor:id,name,username')
                        ->orderBy('title')
                        ->get(['id', 'title', 'mentor_id', 'deleted_at'])
                        ->map(fn (Rubric $rubric) => [
                            'id' => (int) $rubric->id,
                            'title' => (string) $rubric->title,
                            'mentor_id' => $rubric->mentor_id ? (int) $rubric->mentor_id : null,
                            'mentor_name' => (string) ($rubric->mentor?->name ?? ''),
                            'is_archived' => ! is_null($rubric->deleted_at),
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
        ]);
    }

    public function updateAssignment(Request $request, Creation $creation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->isAdminUser($actor), 403, 'ONLY_ADMIN_CAN_ASSIGN_CREATION_REVIEW');

        $validated = $request->validate([
            'is_open_for_review' => ['nullable', 'boolean'],
            'assigned_reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_rubric_id' => ['nullable', 'integer', 'exists:rubrics,id'],
        ]);

        $assignedReviewerId = array_key_exists('assigned_reviewer_id', $validated)
            ? (int) ($validated['assigned_reviewer_id'] ?? 0)
            : null;

        if (! is_null($assignedReviewerId) && $assignedReviewerId > 0) {
            $isMentorReviewer = User::query()
                ->whereKey($assignedReviewerId)
                ->where('role', User::ROLE_MENTOR)
                ->exists();

            if (! $isMentorReviewer) {
                throw ValidationException::withMessages([
                    'assigned_reviewer_id' => 'Reviewer harus user dengan role mentor.',
                ]);
            }
        }

        $previousReviewerId = (int) ($creation->assigned_reviewer_id ?? 0);
        $previousRubricId = (int) ($creation->assigned_rubric_id ?? 0);
        $previousOpenState = (bool) ($creation->is_open_for_review ?? false);

        if (array_key_exists('is_open_for_review', $validated)) {
            $creation->is_open_for_review = (bool) $validated['is_open_for_review'];
        }

        if (array_key_exists('assigned_reviewer_id', $validated)) {
            $creation->assigned_reviewer_id = $assignedReviewerId > 0 ? $assignedReviewerId : null;
        }

        if (array_key_exists('assigned_rubric_id', $validated)) {
            $rubricId = (int) ($validated['assigned_rubric_id'] ?? 0);
            $creation->assigned_rubric_id = $rubricId > 0 ? $rubricId : null;
        }

        if (! $creation->is_open_for_review && in_array((string) $creation->review_status, ['pending', 'none'], true)) {
            $creation->review_status = 'none';
        }

        if ($creation->is_open_for_review && ! $creation->finalReview()->exists() && (string) $creation->review_status === 'none') {
            $creation->review_status = 'pending';
        }

        $creation->save();

        $currentReviewerId = (int) ($creation->assigned_reviewer_id ?? 0);
        $currentRubricId = (int) ($creation->assigned_rubric_id ?? 0);
        $currentOpenState = (bool) ($creation->is_open_for_review ?? false);

        $shouldNotifyReviewer = $currentReviewerId > 0 && (
            $currentReviewerId !== $previousReviewerId
            || $currentRubricId !== $previousRubricId
            || $currentOpenState !== $previousOpenState
        );

        if ($shouldNotifyReviewer) {
            $reviewer = User::query()->whereKey($currentReviewerId)->first();
            if ($reviewer && $reviewer->isMentor()) {
                $rubric = $currentRubricId > 0 ? Rubric::query()->find($currentRubricId) : null;
                $reviewer->notify(new CreationReviewAssignedNotification($creation->fresh(), $actor, $rubric));
            }
        }

        return back()->with('message', 'CREATION_REVIEW_ASSIGNMENT_UPDATED');
    }

    public function submitFinalReview(
        Request $request,
        Creation $creation,
        RubricScoringService $scoring,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->isMentor(), 403, 'ONLY_MENTOR_CAN_SUBMIT_CREATION_REVIEW');
        $this->assertCanPreviewCreation($creation, $user);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,needs_revision'],
            'feedback' => ['nullable', 'string'],
            'selected_levels' => ['required', 'array', 'min:1'],
            'selected_levels.*' => ['nullable', 'integer'],
        ]);

        $rubric = $creation->assigned_rubric_id
            ? Rubric::query()->find((int) $creation->assigned_rubric_id)
            : null;

        if (! $rubric) {
            throw ValidationException::withMessages([
                'selected_levels' => 'Rubric review belum di-assign untuk creation ini.',
            ]);
        }

        $rubric->loadMissing([
            'criteria:id,rubric_id,name,weight,order',
            'levels:id,rubric_id,score_value',
        ]);

        $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($id) => (int) $id)->all();
        $levelsById = $rubric->levels->keyBy('id');

        $selected = [];
        $errors = [];

        foreach (($validated['selected_levels'] ?? []) as $criteriaId => $levelId) {
            $criteriaId = (int) $criteriaId;
            $levelId = (int) ($levelId ?? 0);

            if ($criteriaId <= 0 || ! in_array($criteriaId, $criteriaIds, true)) {
                continue;
            }

            if ($levelId <= 0 || ! $levelsById->has($levelId)) {
                $errors["selected_levels.{$criteriaId}"] = 'Level wajib dipilih untuk semua kriteria.';
                continue;
            }

            $selected[$criteriaId] = $levelId;
        }

        foreach ($criteriaIds as $criteriaId) {
            if (! isset($selected[$criteriaId])) {
                $errors["selected_levels.{$criteriaId}"] = 'Level wajib dipilih untuk semua kriteria.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $result = $scoring->calculate($rubric, $selected);
        $maxRaw = (float) ($result['max_score'] ?? 0);
        $totalRaw = (float) ($result['total'] ?? 0);
        $scorePercent = $maxRaw > 0 ? (int) round(($totalRaw / $maxRaw) * 100) : 0;
        $scorePercent = max(0, min(100, $scorePercent));

        $peerPayload = [
            'rubric_id' => (int) $rubric->id,
            'score_percent' => $scorePercent,
            'status' => (string) $validated['status'],
            'feedback' => $validated['feedback'] ?? null,
            'selected_levels' => $selected,
            'result_breakdown' => [
                'total_raw' => $totalRaw,
                'max_raw' => $maxRaw,
                'percent' => $scorePercent,
                'breakdown' => $result['breakdown'] ?? [],
            ],
            'rubric_snapshot' => $rubric->exportAsJson(),
            'reviewed_at' => now(),
        ];

        $peerReview = CreationPeerReview::query()->updateOrCreate(
            [
                'creation_id' => (int) $creation->id,
                'reviewer_id' => (int) $user->id,
            ],
            $peerPayload
        );

        if ((string) $creation->review_status === 'none') {
            $creation->review_status = 'pending';
            $creation->save();
        }

        return back()->with('message', 'CREATION_FINAL_REVIEW_SAVED');
    }

    public function publishOfficialReview(Request $request, Creation $creation, CreationPeerReview $peerReview): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->isAdminUser($actor), 403, 'ONLY_ADMIN_CAN_PUBLISH_OFFICIAL_CREATION_REVIEW');
        abort_unless((int) $peerReview->creation_id === (int) $creation->id, 404, 'CREATION_REVIEW_NOT_FOUND');

        $this->syncOfficialReviewFromPeer($creation, $peerReview, $actor);

        return back()->with('message', 'CREATION_OFFICIAL_REVIEW_PUBLISHED');
    }

    public function publishOfficialAggregate(Request $request, Creation $creation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->isAdminUser($actor), 403, 'ONLY_ADMIN_CAN_PUBLISH_OFFICIAL_CREATION_REVIEW');

        $validated = $request->validate([
            'peer_review_ids' => ['nullable', 'array', 'min:2'],
            'peer_review_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $requestedPeerReviewIds = collect($validated['peer_review_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $peerReviewsQuery = CreationPeerReview::query()
            ->where('creation_id', (int) $creation->id)
            ->with(['reviewer:id,name,username', 'rubric:id,title'])
            ->orderBy('reviewed_at');

        if ($requestedPeerReviewIds->isNotEmpty()) {
            $peerReviewsQuery->whereIn('id', $requestedPeerReviewIds->all());
        }

        $peerReviews = $peerReviewsQuery->get();

        if ($peerReviews->count() < 2) {
            throw ValidationException::withMessages([
                'peer_review_ids' => 'Minimal butuh 2 review mentor untuk publish kalkulasi gabungan.',
            ]);
        }

        if ($requestedPeerReviewIds->isNotEmpty() && $peerReviews->count() !== $requestedPeerReviewIds->count()) {
            throw ValidationException::withMessages([
                'peer_review_ids' => 'Sebagian review yang dipilih tidak valid untuk creation ini.',
            ]);
        }

        $this->syncOfficialReviewFromAggregate($creation, $peerReviews, $actor);

        return back()->with('message', 'CREATION_OFFICIAL_REVIEW_PUBLISHED');
    }

    private function assertCanPreviewCreation(Creation $creation, User $actor): void
    {
        if ($this->isAdminUser($actor)) {
            return;
        }

        abort_unless($actor->isMentor(), 403, 'ROLE_CANNOT_REVIEW_CREATION');
        abort_if(! (bool) $creation->is_open_for_review, 403, 'CREATION_REVIEW_IS_CLOSED');

        $mentorJobId = $this->requireMentorJobId($actor);
        $creation->loadMissing('user:id,job_id');

        $creatorJobId = (int) ($creation->user?->job_id ?? 0);
        $assignedReviewerId = (int) ($creation->assigned_reviewer_id ?? 0);

        $isAssigned = $assignedReviewerId > 0 && $assignedReviewerId === (int) $actor->id;
        $isSameJob = $creatorJobId > 0 && $creatorJobId === $mentorJobId;

        abort_unless($isAssigned || $isSameJob, 403, 'MENTOR_CANNOT_REVIEW_CREATION_OUTSIDE_SCOPE');
    }

    private function requireMentorJobId(User $mentor): int
    {
        $jobId = (int) ($mentor->job_id ?? 0);
        abort_if($jobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);
        return $jobId;
    }

    private function isAdminUser(User $user): bool
    {
        return $user->hasRole([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN]);
    }

    private function buildRubricEvaluationPayload(Rubric $rubric): array
    {
        $rubric->loadMissing([
            'criteria',
            'levels',
        ]);

        $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($id) => (int) $id)->all();
        $descriptions = count($criteriaIds)
            ? RubricDescription::query()->whereIn('criteria_id', $criteriaIds)->get()
            : collect();

        $matrix = [];
        foreach ($descriptions as $desc) {
            $matrix[(int) $desc->criteria_id][(int) $desc->level_id] = $desc->description;
        }

        return [
            'rubric' => [
                'id' => (int) $rubric->id,
                'title' => (string) $rubric->title,
                'max_score' => (float) $rubric->max_score,
            ],
            'criteria' => $rubric->criteria->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'weight' => (float) $c->weight,
                'order' => (int) $c->order,
            ])->values()->all(),
            'levels' => $rubric->levels->map(fn ($l) => [
                'id' => (int) $l->id,
                'level' => (int) $l->level,
                'label' => (string) $l->label,
                'score_value' => (float) $l->score_value,
            ])->values()->all(),
            'matrix' => $matrix,
        ];
    }

    private function serializeCreation(Creation $creation): array
    {
        return [
            'id' => (int) $creation->id,
            'title' => (string) $creation->title,
            'description' => (string) $creation->description,
            'content' => (string) ($creation->content ?? ''),
            'link' => (string) ($creation->link ?? ''),
            'category' => (string) ($creation->category ?? ''),
            'status' => (string) ($creation->status ?? 'crafting'),
            'progress' => (int) ($creation->progress ?? 0),
            'is_open_for_review' => (bool) ($creation->is_open_for_review ?? false),
            'review_status' => (string) ($creation->review_status ?? 'none'),
            'assigned_reviewer_id' => $creation->assigned_reviewer_id ? (int) $creation->assigned_reviewer_id : null,
            'assigned_rubric_id' => $creation->assigned_rubric_id ? (int) $creation->assigned_rubric_id : null,
            'creator' => [
                'id' => (int) ($creation->user?->id ?? 0),
                'name' => (string) ($creation->user?->name ?? ''),
                'username' => (string) ($creation->user?->username ?? ''),
                'role' => (string) ($creation->user?->role ?? ''),
                'job_id' => $creation->user?->job_id ? (int) $creation->user->job_id : null,
            ],
            'photos' => $creation->photos
                ->map(fn ($photo) => [
                    'id' => (int) $photo->id,
                    'url' => (string) $photo->url,
                ])
                ->values()
                ->all(),
        ];
    }

    private function serializeReview(CreationReview $review): array
    {
        $isAggregate = str_starts_with((string) data_get($review->result_breakdown, 'mode', ''), 'aggregate_');
        $aggregateReviewers = collect(data_get($review->result_breakdown, 'reviewers', []))
            ->map(function ($item) {
                $name = (string) data_get($item, 'username', '');
                if ($name === '') {
                    $name = (string) data_get($item, 'name', '');
                }

                return $name;
            })
            ->filter()
            ->values();

        $reviewerLabel = $isAggregate
            ? sprintf('AGGREGATED (%d REVIEWERS)', max(2, $aggregateReviewers->count()))
            : (string) ($review->reviewer?->username ?: $review->reviewer?->name ?: '-');

        return [
            'id' => (int) $review->id,
            'reviewer_id' => (int) $review->reviewer_id,
            'rubric_id' => (int) $review->rubric_id,
            'score_percent' => (int) $review->score_percent,
            'status' => (string) $review->status,
            'feedback' => (string) ($review->feedback ?? ''),
            'is_aggregate' => $isAggregate,
            'reviewer_label' => $reviewerLabel,
            'aggregate' => $isAggregate ? [
                'count' => max(2, $aggregateReviewers->count()),
                'reviewer_names' => $aggregateReviewers->all(),
                'peer_review_ids' => collect(data_get($review->result_breakdown, 'peer_review_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all(),
            ] : null,
            'selected_levels' => collect($review->selected_levels ?? [])
                ->mapWithKeys(fn ($levelId, $criteriaId) => [(string) $criteriaId => (int) $levelId])
                ->all(),
            'result_breakdown' => $review->result_breakdown ?? [],
            'rubric_snapshot' => $review->rubric_snapshot ?? null,
            'reviewed_at' => $review->reviewed_at?->toISOString(),
            'published_at' => $review->published_at?->toISOString(),
            'reviewer' => [
                'id' => (int) ($review->reviewer?->id ?? 0),
                'name' => (string) ($review->reviewer?->name ?? ''),
                'username' => (string) ($review->reviewer?->username ?? ''),
            ],
            'publisher' => [
                'id' => (int) ($review->publisher?->id ?? 0),
                'name' => (string) ($review->publisher?->name ?? ''),
                'username' => (string) ($review->publisher?->username ?? ''),
            ],
            'rubric' => [
                'id' => (int) ($review->rubric?->id ?? 0),
                'title' => (string) ($review->rubric?->title ?? ''),
            ],
        ];
    }

    private function serializePeerReview(CreationPeerReview $review): array
    {
        return [
            'id' => (int) $review->id,
            'creation_id' => (int) $review->creation_id,
            'reviewer_id' => (int) $review->reviewer_id,
            'rubric_id' => (int) $review->rubric_id,
            'score_percent' => (int) $review->score_percent,
            'status' => (string) $review->status,
            'feedback' => (string) ($review->feedback ?? ''),
            'selected_levels' => collect($review->selected_levels ?? [])
                ->mapWithKeys(fn ($levelId, $criteriaId) => [(string) $criteriaId => (int) $levelId])
                ->all(),
            'result_breakdown' => $review->result_breakdown ?? [],
            'rubric_snapshot' => $review->rubric_snapshot ?? null,
            'reviewed_at' => $review->reviewed_at?->toISOString(),
            'reviewer' => [
                'id' => (int) ($review->reviewer?->id ?? 0),
                'name' => (string) ($review->reviewer?->name ?? ''),
                'username' => (string) ($review->reviewer?->username ?? ''),
            ],
            'rubric' => [
                'id' => (int) ($review->rubric?->id ?? 0),
                'title' => (string) ($review->rubric?->title ?? ''),
            ],
        ];
    }

    private function serializePublicationLog(CreationReviewPublication $log): array
    {
        return [
            'id' => (int) $log->id,
            'creation_id' => (int) $log->creation_id,
            'peer_review_id' => $log->peer_review_id ? (int) $log->peer_review_id : null,
            'official_review_id' => $log->official_review_id ? (int) $log->official_review_id : null,
            'published_by' => $log->published_by ? (int) $log->published_by : null,
            'published_at' => $log->published_at?->toISOString(),
            'publisher' => [
                'id' => (int) ($log->publisher?->id ?? 0),
                'name' => (string) ($log->publisher?->name ?? ''),
                'username' => (string) ($log->publisher?->username ?? ''),
            ],
            'reviewer' => [
                'id' => (int) ($log->peerReview?->reviewer?->id ?? 0),
                'name' => (string) ($log->peerReview?->reviewer?->name ?? ''),
                'username' => (string) ($log->peerReview?->reviewer?->username ?? ''),
            ],
            'payload' => $log->payload ?? [],
        ];
    }

    private function syncOfficialReviewFromPeer(Creation $creation, CreationPeerReview $peerReview, ?User $publisher = null): void
    {
        $previousOfficial = CreationReview::query()
            ->where('creation_id', (int) $creation->id)
            ->first();

        $official = CreationReview::query()->updateOrCreate(
            ['creation_id' => (int) $creation->id],
            [
                'reviewer_id' => (int) $peerReview->reviewer_id,
                'rubric_id' => (int) $peerReview->rubric_id,
                'score_percent' => (int) $peerReview->score_percent,
                'status' => (string) $peerReview->status,
                'feedback' => $peerReview->feedback,
                'selected_levels' => $peerReview->selected_levels ?? [],
                'result_breakdown' => $peerReview->result_breakdown ?? [],
                'rubric_snapshot' => $peerReview->rubric_snapshot ?? null,
                'source_peer_review_id' => (int) $peerReview->id,
                'published_by' => $publisher?->id,
                'published_at' => now(),
                'reviewed_at' => $peerReview->reviewed_at ?? now(),
            ]
        );

        $creation->review_status = (string) $peerReview->status;
        $creation->save();

        CreationReviewPublication::query()->create([
            'creation_id' => (int) $creation->id,
            'peer_review_id' => (int) $peerReview->id,
            'official_review_id' => (int) $official->id,
            'published_by' => $publisher?->id,
            'published_at' => now(),
            'payload' => [
                'current' => [
                    'score_percent' => (int) $peerReview->score_percent,
                    'status' => (string) $peerReview->status,
                    'reviewer_id' => (int) $peerReview->reviewer_id,
                    'rubric_id' => (int) $peerReview->rubric_id,
                ],
                'previous' => $previousOfficial ? [
                    'score_percent' => (int) $previousOfficial->score_percent,
                    'status' => (string) $previousOfficial->status,
                    'reviewer_id' => (int) $previousOfficial->reviewer_id,
                    'rubric_id' => (int) $previousOfficial->rubric_id,
                ] : null,
            ],
        ]);

        $creation->loadMissing('user:id,name,username');
        $creator = $creation->user;
        if ($creator && (! $publisher || (int) $creator->id !== (int) $publisher->id)) {
            $creator->notify(new CreationReviewPublishedNotification($creation, $official, $publisher));
        }
    }

    private function syncOfficialReviewFromAggregate(Creation $creation, Collection $peerReviews, User $publisher): void
    {
        $previousOfficial = CreationReview::query()
            ->where('creation_id', (int) $creation->id)
            ->first();

        /** @var CreationPeerReview $firstReview */
        $firstReview = $peerReviews->firstOrFail();
        $rubric = Rubric::query()
            ->with(['levels:id,rubric_id,score_value'])
            ->findOrFail((int) $firstReview->rubric_id);

        $averageScore = (int) round((float) $peerReviews->avg('score_percent'));
        $averageScore = max(0, min(100, $averageScore));

        $status = $this->resolveAggregateStatus($peerReviews);
        $selectedLevels = $this->resolveAggregateSelectedLevels($rubric, $peerReviews);
        $feedback = $this->buildAggregateFeedback($peerReviews);
        $reviewedAt = $peerReviews
            ->pluck('reviewed_at')
            ->filter()
            ->max();

        $official = CreationReview::query()->updateOrCreate(
            ['creation_id' => (int) $creation->id],
            [
                'reviewer_id' => (int) $publisher->id,
                'rubric_id' => (int) $rubric->id,
                'score_percent' => $averageScore,
                'status' => $status,
                'feedback' => $feedback,
                'selected_levels' => $selectedLevels,
                'result_breakdown' => [
                    'mode' => 'aggregate_reviews',
                    'calculation' => [
                        'type' => 'average_score_percent',
                        'sample_size' => (int) $peerReviews->count(),
                    ],
                    'peer_review_ids' => $peerReviews->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'reviewers' => $peerReviews->map(fn (CreationPeerReview $review) => [
                        'reviewer_id' => (int) $review->reviewer_id,
                        'name' => (string) ($review->reviewer?->name ?? ''),
                        'username' => (string) ($review->reviewer?->username ?? ''),
                        'score_percent' => (int) $review->score_percent,
                        'status' => (string) $review->status,
                        'reviewed_at' => $review->reviewed_at?->toISOString(),
                    ])->values()->all(),
                ],
                'rubric_snapshot' => $firstReview->rubric_snapshot ?? $rubric->exportAsJson(),
                'source_peer_review_id' => null,
                'published_by' => (int) $publisher->id,
                'published_at' => now(),
                'reviewed_at' => $reviewedAt ?? now(),
            ]
        );

        $creation->review_status = $status;
        $creation->save();

        CreationReviewPublication::query()->create([
            'creation_id' => (int) $creation->id,
            'peer_review_id' => null,
            'official_review_id' => (int) $official->id,
            'published_by' => (int) $publisher->id,
            'published_at' => now(),
            'payload' => [
                'mode' => 'aggregate_reviews',
                'peer_review_ids' => $peerReviews->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'current' => [
                    'score_percent' => $averageScore,
                    'status' => $status,
                    'reviewer_id' => (int) $publisher->id,
                    'rubric_id' => (int) $rubric->id,
                ],
                'previous' => $previousOfficial ? [
                    'score_percent' => (int) $previousOfficial->score_percent,
                    'status' => (string) $previousOfficial->status,
                    'reviewer_id' => (int) $previousOfficial->reviewer_id,
                    'rubric_id' => (int) $previousOfficial->rubric_id,
                ] : null,
            ],
        ]);

        $creation->loadMissing('user:id,name,username');
        $creator = $creation->user;
        if ($creator && (int) $creator->id !== (int) $publisher->id) {
            $creator->notify(new CreationReviewPublishedNotification($creation, $official, $publisher));
        }
    }

    private function resolveAggregateStatus(Collection $peerReviews): string
    {
        $hasNeedsRevision = $peerReviews
            ->contains(fn (CreationPeerReview $review) => (string) $review->status === CreationReview::STATUS_NEEDS_REVISION);

        return $hasNeedsRevision
            ? CreationReview::STATUS_NEEDS_REVISION
            : CreationReview::STATUS_APPROVED;
    }

    private function resolveAggregateSelectedLevels(Rubric $rubric, Collection $peerReviews): array
    {
        $rubric->loadMissing(['criteria:id,rubric_id', 'levels:id,rubric_id,score_value']);
        $levelsById = $rubric->levels->keyBy('id');
        $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($id) => (int) $id)->values();

        $selected = [];
        foreach ($criteriaIds as $criteriaId) {
            $scores = $peerReviews
                ->map(function (CreationPeerReview $review) use ($criteriaId, $levelsById) {
                    $levelId = (int) data_get($review->selected_levels, (string) $criteriaId, 0);
                    if ($levelId <= 0 || ! $levelsById->has($levelId)) {
                        return null;
                    }

                    return (float) ($levelsById->get($levelId)->score_value ?? 0);
                })
                ->filter(fn ($value) => $value !== null)
                ->values();

            if ($scores->isEmpty()) {
                continue;
            }

            $avgScoreValue = (float) $scores->avg();
            $closestLevelId = $this->resolveClosestLevelId($rubric->levels, $avgScoreValue);
            if ($closestLevelId > 0) {
                $selected[(string) $criteriaId] = $closestLevelId;
            }
        }

        return $selected;
    }

    private function resolveClosestLevelId(Collection $levels, float $targetScore): int
    {
        $bestLevel = $levels
            ->sortBy(function ($level) use ($targetScore) {
                $levelScore = (float) ($level->score_value ?? 0);
                return abs($levelScore - $targetScore);
            })
            ->first();

        return $bestLevel ? (int) $bestLevel->id : 0;
    }

    private function buildAggregateFeedback(Collection $peerReviews): string
    {
        $feedbackLines = $peerReviews
            ->map(function (CreationPeerReview $review) {
                $reviewerName = (string) ($review->reviewer?->username ?: $review->reviewer?->name ?: ('reviewer#' . $review->reviewer_id));
                $status = strtoupper((string) $review->status);
                $score = (int) $review->score_percent;
                $feedback = trim((string) ($review->feedback ?? ''));
                if ($feedback === '') {
                    return "- {$reviewerName} ({$status}, {$score}%): -";
                }

                return "- {$reviewerName} ({$status}, {$score}%): {$feedback}";
            })
            ->values()
            ->all();

        return implode("\n", $feedbackLines);
    }
}
