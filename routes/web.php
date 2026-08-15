<?php

use App\Http\Controllers\AdminErrorLogController;
use App\Http\Controllers\AdminEventController;
use App\Http\Controllers\AdminGuideController;
use App\Http\Controllers\AdminJobRoleController;
use App\Http\Controllers\AdminProfileSkinController;
use App\Http\Controllers\AdminQuestController;
use App\Http\Controllers\AdminShopItemController;
use App\Http\Controllers\ProfileSkinController;
use App\Http\Controllers\AdminStudyGroupController;
use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\AdminSubmissionManagementController;
use App\Http\Controllers\AdminTaskBankController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminDailyQuestDefinitionController;
use App\Http\Controllers\AdminCreationReviewController;
use App\Http\Controllers\AdminOptionalQuestAiController;
use App\Http\Controllers\DoopLabLogbookController;
use App\Http\Controllers\DoopLabDashboardController;
use App\Http\Controllers\DoopLabRoadmapController;
use App\Http\Controllers\DoopLabRoadmapEnrollmentController;
use App\Http\Controllers\DoopLabTodoController;
use App\Http\Controllers\ChatImageUploadController;
use App\Models\User;
use App\Http\Controllers\CreationApiController;
use App\Http\Controllers\CreationInteractionController;
use App\Http\Controllers\CreationCollaborationController;
use App\Http\Controllers\DailyQuestController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\CreationPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HallOfCreationApiController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationDispatchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\RubricController;
use App\Http\Controllers\RubricCriteriaController;
use App\Http\Controllers\RubricLevelController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserEventController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/hall-of-creations', [HallOfCreationApiController::class, 'index'])->name('hall.index');
    Route::get('/hall-of-creations/{creation}', [HallOfCreationApiController::class, 'show'])->name('hall.show');
    Route::get('/creations/{creation}/insights', [CreationInteractionController::class, 'insights'])->name('creations.insights.index');
});

Route::middleware('auth')->prefix('api')->name('api.')->group(function () {
    Route::get('/users/transfer-recipients', [ProfileController::class, 'transferRecipients'])->name('users.transfer-recipients');
    Route::get('/creations', [CreationApiController::class, 'index'])->name('creations.index');
    Route::get('/profile/creations', [CreationApiController::class, 'index'])->name('profile.creations.index');
    Route::post('/creations', [CreationApiController::class, 'store'])->name('creations.store');
    Route::get('/creations/{creation}', [CreationApiController::class, 'show'])->name('creations.show');
    Route::put('/creations/{creation}', [CreationApiController::class, 'update'])->name('creations.update');
    Route::delete('/creations/{creation}', [CreationApiController::class, 'destroy'])->name('creations.destroy');
    Route::post('/creations/{creation}/hire-mentor', [CreationApiController::class, 'hireMentor'])->name('creations.hire-mentor');
    Route::post('/dooplab/hire-mentor', [CreationApiController::class, 'hireDirectMentor'])->name('dooplab.hire-mentor');
    Route::post('/creation-mentor-invites/{collaborationRequest}/accept', [CreationApiController::class, 'acceptMentorInvite'])->name('creations.mentor-invites.accept');
    Route::post('/creation-mentor-invites/{collaborationRequest}/reject', [CreationApiController::class, 'rejectMentorInvite'])->name('creations.mentor-invites.reject');
    Route::post('/creation-mentor-invites/{collaborationRequest}/cancel', [CreationApiController::class, 'cancelMentorInvite'])->name('creations.mentor-invites.cancel');

    Route::post('/creations/{creation}/appreciate', [CreationInteractionController::class, 'appreciate'])->name('creations.appreciate.store');
    Route::delete('/creations/{creation}/appreciate', [CreationInteractionController::class, 'removeAppreciation'])->name('creations.appreciate.destroy');
    Route::post('/creations/{creation}/insights', [CreationInteractionController::class, 'storeInsight'])->name('creations.insights.store');
    Route::post('/creations/{creation}/collaboration-requests', [CreationCollaborationController::class, 'storeRequest'])->name('creations.collaboration-requests.store');
    Route::post('/creations/{creation}/collaboration-requests/{collaborationRequest}/approve', [CreationCollaborationController::class, 'approve'])->name('creations.collaboration-requests.approve');
    Route::post('/creations/{creation}/collaboration-requests/{collaborationRequest}/reject', [CreationCollaborationController::class, 'reject'])->name('creations.collaboration-requests.reject');
    Route::delete('/creations/{creation}/collaboration-requests/{collaborationRequest}', [CreationCollaborationController::class, 'withdraw'])->name('creations.collaboration-requests.withdraw');
    Route::delete('/creations/{creation}/collaborators/{user}', [CreationCollaborationController::class, 'removeCollaborator'])->name('creations.collaborators.destroy');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
});

Route::get('/', [HomeController::class, 'index'])->name('lobby');
Route::get('/landing', [HomeController::class, 'landing'])->name('landing');
Route::get('/dooplab', function () {
    if (! auth()->check()) {
        return redirect()->route('landing');
    }

    return Inertia::render('DoopLab/Index', [
        'hasAccess' => (bool) auth()->user()?->canAccessDoopLab(),
        'telemetryStats' => [
            'total_member' => User::query()->where('role', User::ROLE_STUDENT)->count(),
            'total_mentor' => User::query()->where('role', User::ROLE_MENTOR)->count(),
        ],
    ]);
})->name('dooplab.index');
Route::get('/public/events/{uuid}', [PublicEventController::class, 'show'])->name('public.events.show');
Route::get('/robots.txt', function () {
    $content = implode(PHP_EOL, [
        'User-agent: *',
        'Allow: /',
        'Sitemap: '.url('/sitemap.xml'),
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::get('/sitemap.xml', function () {
    $urls = [
        [
            'loc' => route('lobby'),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ],
    ];

    if (Route::has('login')) {
        $urls[] = [
            'loc' => route('login'),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ];
    }

    if (Route::has('register')) {
        $urls[] = [
            'loc' => route('register'),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($urls as $item) {
        $xml .= '<url>';
        $xml .= '<loc>'.e($item['loc']).'</loc>';
        $xml .= '<changefreq>'.$item['changefreq'].'</changefreq>';
        $xml .= '<priority>'.$item['priority'].'</priority>';
        $xml .= '</url>';
    }

    $xml .= '</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

if (app()->environment(['local', 'testing'])) {
    Route::get('/redis-test', function () {
        Redis::set('test', 'ok');

        return Redis::get('test');
    });

    Route::get('/simulate-500', function () {
        throw new \Exception('Simulasi error 500 untuk pengujian alert');
    });
}

Route::get('/hall-of-creations', [CreationPageController::class, 'hallIndex'])->name('hall.creations.index');

// Backward-compat: old /hall-of-creations/{id} links -> redirect to slug URL
Route::get('/hall-of-creations/{id}', function ($id) {
    if (! ctype_digit((string) $id)) {
        abort(404);
    }
    $creation = \App\Models\Creation::query()->find((int) $id);
    abort_unless($creation && $creation->slug, 404);
    return redirect()->route('hall.creations.show', ['creation' => $creation->slug], 301);
})->whereNumber('id');

Route::get('/hall-of-creations/{creation}', [CreationPageController::class, 'show'])->name('hall.creations.show');
Route::get('/hall-of-creations/{creation}/review', [CreationPageController::class, 'showReview'])->name('hall.creations.review');
Route::get('/profiles/{user:username}', [ProfileController::class, 'show'])->name('profiles.show');

Route::middleware('auth')->group(function () {
    Route::middleware('role:admin,mentor')->group(function () {
        Route::get('/admin/profile', [ProfileController::class, 'adminEdit'])->name('admin.profile.edit');
        Route::patch('/admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    });

    Route::get('/dooplab/dashboard', [DoopLabDashboardController::class, 'index'])->name('dooplab.dashboard');
    Route::get('/dooplab/roadmaps', [DoopLabRoadmapController::class, 'index'])->name('dooplab.roadmaps.index');
    Route::post('/dooplab/roadmaps', [DoopLabRoadmapController::class, 'storeRoadmap'])->name('dooplab.roadmaps.store');
    Route::patch('/dooplab/roadmaps/{roadmap}', [DoopLabRoadmapController::class, 'updateRoadmap'])->name('dooplab.roadmaps.update');
    Route::delete('/dooplab/roadmaps/{roadmap}', [DoopLabRoadmapController::class, 'destroyRoadmap'])->name('dooplab.roadmaps.destroy');
    Route::post('/dooplab/roadmaps/{roadmap}/sections', [DoopLabRoadmapController::class, 'storeSection'])->name('dooplab.roadmaps.sections.store');
    Route::patch('/dooplab/roadmap-sections/{section}', [DoopLabRoadmapController::class, 'updateSection'])->name('dooplab.roadmaps.sections.update');
    Route::delete('/dooplab/roadmap-sections/{section}', [DoopLabRoadmapController::class, 'destroySection'])->name('dooplab.roadmaps.sections.destroy');
    Route::post('/dooplab/roadmaps/{roadmap}/text-blocks', [DoopLabRoadmapController::class, 'storeTextBlock'])->name('dooplab.roadmaps.text-blocks.store');
    Route::patch('/dooplab/roadmap-text-blocks/{textBlock}', [DoopLabRoadmapController::class, 'updateTextBlock'])->name('dooplab.roadmaps.text-blocks.update');
    Route::delete('/dooplab/roadmap-text-blocks/{textBlock}', [DoopLabRoadmapController::class, 'destroyTextBlock'])->name('dooplab.roadmaps.text-blocks.destroy');
    Route::post('/dooplab/roadmaps/{roadmap}/nodes', [DoopLabRoadmapController::class, 'storeNode'])->name('dooplab.roadmaps.nodes.store');
    Route::patch('/dooplab/roadmap-nodes/{node}', [DoopLabRoadmapController::class, 'updateNode'])->name('dooplab.roadmaps.nodes.update');
    Route::delete('/dooplab/roadmap-nodes/{node}', [DoopLabRoadmapController::class, 'destroyNode'])->name('dooplab.roadmaps.nodes.destroy');
    Route::post('/dooplab/roadmaps/{roadmap}/edges', [DoopLabRoadmapController::class, 'storeEdge'])->name('dooplab.roadmaps.edges.store');
    Route::delete('/dooplab/roadmap-edges/{edge}', [DoopLabRoadmapController::class, 'destroyEdge'])->name('dooplab.roadmaps.edges.destroy');
    Route::patch('/dooplab/roadmap-edges/{edge}', [DoopLabRoadmapController::class, 'updateEdge'])->name('dooplab.roadmaps.edges.update');
    Route::get('/dooplab/my-paths', [DoopLabRoadmapEnrollmentController::class, 'index'])->name('dooplab.roadmaps.enrollments.index');
    Route::get('/dooplab/enrollments/{enrollment}', [DoopLabRoadmapEnrollmentController::class, 'show'])->name('dooplab.roadmaps.enrollments.show');
    Route::post('/dooplab/enrollments', [DoopLabRoadmapEnrollmentController::class, 'store'])->name('dooplab.roadmaps.enrollments.store');
    Route::delete('/dooplab/enrollments/{enrollment}', [DoopLabRoadmapEnrollmentController::class, 'destroy'])->name('dooplab.roadmaps.enrollments.destroy');
    Route::post('/dooplab/enrollments/{enrollment}/nodes/{nodeUuid}/submit', [DoopLabRoadmapEnrollmentController::class, 'submit'])->name('dooplab.roadmaps.enrollments.submit');
    Route::post('/dooplab/enrollments/{enrollment}/nodes/{nodeUuid}/review', [DoopLabRoadmapEnrollmentController::class, 'review'])->name('dooplab.roadmaps.enrollments.review');
    Route::post('/dooplab/enrollments/{enrollment}/nodes/{nodeUuid}/unlock', [DoopLabRoadmapEnrollmentController::class, 'unlock'])->name('dooplab.roadmaps.enrollments.unlock');
    Route::post('/dooplab/enrollments/{enrollment}/nodes/{nodeUuid}/lock', [DoopLabRoadmapEnrollmentController::class, 'lock'])->name('dooplab.roadmaps.enrollments.lock');
    Route::post('/dooplab/todos', [DoopLabTodoController::class, 'store'])->name('dooplab.todos.store');
    Route::patch('/dooplab/todos/{todo}', [DoopLabTodoController::class, 'update'])->name('dooplab.todos.update');
    Route::patch('/dooplab/todos/{todo}/toggle', [DoopLabTodoController::class, 'toggle'])->name('dooplab.todos.toggle');
    Route::patch('/dooplab/todos/{todo}/submit-review', [DoopLabTodoController::class, 'submitForReview'])->name('dooplab.todos.submit-review');
    Route::patch('/dooplab/todos/{todo}/review', [DoopLabTodoController::class, 'reviewCheckpoint'])->name('dooplab.todos.review');
    Route::delete('/dooplab/todos/{todo}', [DoopLabTodoController::class, 'destroy'])->name('dooplab.todos.destroy');
    Route::post('/dooplab/todos/{todo}/notes', [DoopLabTodoController::class, 'storeNote'])->name('dooplab.todos.notes.store');
    Route::post('/dooplab/logbooks', [DoopLabLogbookController::class, 'store'])->name('dooplab.logbooks.store');
    Route::post('/dooplab/logbooks/assign', [DoopLabLogbookController::class, 'assign'])->name('dooplab.logbooks.assign');
    Route::patch('/dooplab/logbooks/{logbook}', [DoopLabLogbookController::class, 'update'])->name('dooplab.logbooks.update');
    Route::delete('/dooplab/logbooks/{logbook}', [DoopLabLogbookController::class, 'destroy'])->name('dooplab.logbooks.destroy');
    Route::post('/dooplab/logbooks/{logbook}/entries', [DoopLabLogbookController::class, 'storeEntry'])->name('dooplab.logbooks.entries.store');
    Route::post('/dooplab/logbooks/{logbook}/entries/{entry}', [DoopLabLogbookController::class, 'updateEntry'])->name('dooplab.logbooks.entries.update-post');
    Route::patch('/dooplab/logbooks/{logbook}/entries/{entry}', [DoopLabLogbookController::class, 'updateEntry'])->name('dooplab.logbooks.entries.update');
    Route::patch('/dooplab/logbooks/{logbook}/entries/{entry}/approve', [DoopLabLogbookController::class, 'approveEntry'])->name('dooplab.logbooks.entries.approve');
    Route::delete('/dooplab/logbooks/{logbook}/entries/{entry}', [DoopLabLogbookController::class, 'destroyEntry'])->name('dooplab.logbooks.entries.destroy');

    Route::middleware('student_area')->group(function () {
        Route::get('/my-creations', [CreationPageController::class, 'index'])->name('creations.index');
        Route::redirect('/my-creation', '/profile/creations', 301);
        Route::get('/profile/creations', [CreationPageController::class, 'profileCreations'])->name('profile.creations');
        Route::get('/profile/creations/create', [CreationPageController::class, 'create'])->name('profile.creations.create');
        Route::get('/profile/creations/{creation:slug}/edit', [CreationPageController::class, 'edit'])->name('profile.creations.edit');

        Route::get('/profile', [ProfileController::class, 'dashboard'])->name('profile.dashboard');
        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::post('/profile/skins/{skin}/activate', [ProfileSkinController::class, 'activate'])->name('profile.skins.activate');
        Route::delete('/profile/skins/active', [ProfileSkinController::class, 'deactivate'])->name('profile.skins.deactivate');

        Route::get('/quests/{quest}', [QuestController::class, 'show'])->name('quests.show');
        Route::post('/quests/{quest}/platforming-progress', [QuestController::class, 'savePlatformingProgress'])->name('quests.platforming-progress.save');
        Route::get('/quests/{quest}/platforming-progress', [QuestController::class, 'loadPlatformingProgress'])->name('quests.platforming-progress.load');
        Route::post('/quests/{quest}/unlock-late', [QuestController::class, 'unlockLate'])->name('quests.unlock-late');
        Route::post('/quests/{quest}/unlock-retake', [QuestController::class, 'unlockRetake'])->name('quests.unlock-retake');
        Route::post('/quests/{quest}/submissions', [SubmissionController::class, 'store'])
            ->middleware('verified')
            ->name('submissions.store');
        Route::put('/quests/{quest}/exam-draft', [SubmissionController::class, 'saveExamDraft'])
            ->middleware('verified')
            ->name('quests.exam-draft.save');
        Route::get('/submissions/{submission}', [SubmissionController::class, 'showSubmission'])
            ->name('submissions.show');
        Route::put('/submissions/{uuid}', [SubmissionController::class, 'update'])
            ->middleware('verified')
            ->name('submissions.update');
        Route::get('/quests-user', [QuestController::class, 'userIndex'])->name('quests.user.index');

        // --- USER AREA ---
        Route::get('/study-groups', [StudyGroupController::class, 'index'])->name('groups.index');
        Route::get('/study-groups/{uuid}', [StudyGroupController::class, 'show'])->name('groups.show');
        Route::post('/study-groups/join', [StudyGroupController::class, 'join'])->name('groups.join');
        Route::post('/study-groups/{uuid}/leave', [StudyGroupController::class, 'leave'])->name('groups.leave');
        Route::get('/guides', [GuideController::class, 'userIndex'])->name('guides.user.index');
        Route::get('/guides/{guide}', [GuideController::class, 'userShow'])->name('guides.user.show');
        Route::get('/events', [UserEventController::class, 'index'])->name('events.user.index');
        Route::get('/events/{event:uuid}', [UserEventController::class, 'show'])->name('events.show');
        Route::get('/events/{event:uuid}/attendance/qr/{token}', [UserEventController::class, 'qrAttend'])->name('events.attendance.qr');
        Route::post('/events/{event:uuid}/attendance/self', [UserEventController::class, 'selfAttend'])->name('events.attendance.self');
        Route::post('/events/{event:uuid}/attendance/code', [UserEventController::class, 'codeAttend'])->name('events.attendance.code');
        Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/shop/items/{item}/purchase', [ShopController::class, 'purchase'])
            ->middleware('verified')
            ->name('shop.purchase');
        Route::post('/shop/gold-transfer', [ShopController::class, 'transfer'])
            ->middleware('verified')
            ->name('shop.gold-transfer');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
        Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/chat', [NotificationDispatchController::class, 'chat'])->name('notifications.chat');
        Route::post('/chat/images', [ChatImageUploadController::class, 'store'])->name('chat.images.store');
        Route::post('/daily-quests/{dailyQuest}/claim', [DailyQuestController::class, 'claim'])
            ->middleware('verified')
            ->name('daily-quests.claim');
    });
});

Route::middleware(['auth', 'verified', 'role:admin,mentor'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/dashboard', function () {
        return redirect()->route('dashboard');
    })->name('admin.dashboard');

    Route::get('/quests', [QuestController::class, 'index'])->name('quests.index');
    Route::post('/quests', [QuestController::class, 'store'])->name('quests.store');
    Route::patch('/quests/{quest}', [QuestController::class, 'update'])->name('quests.update');
    Route::patch('/quests/{uuid}/restore', [QuestController::class, 'restore'])->name('quests.restore');
    Route::delete('/quests/{uuid}/force', [QuestController::class, 'forceDestroy'])->name('quests.force-destroy');
    Route::delete('/quests/{quest}', [QuestController::class, 'destroy'])->name('quests.destroy');

    Route::get('/quests/{quest}/submissions', [AdminQuestController::class, 'submissions'])
        ->name('admin.quests.submissions');
    Route::get('/quests/{quest}/submissions/download-files', [AdminQuestController::class, 'downloadSubmissionFiles'])
        ->name('admin.quests.submissions.download-files');
    Route::get('/submissions/{submission}/inspect', [AdminSubmissionController::class, 'inspect'])
        ->name('admin.submissions.inspect');
    Route::get('/submissions/{submission}/file', [AdminSubmissionController::class, 'previewFile'])
        ->name('admin.submissions.file');
    Route::post('/submissions/{submission}/verdict', [AdminSubmissionController::class, 'verdict'])
        ->name('admin.submissions.verdict');
    Route::post('/submissions/{submission}/check-ai/preview', [AdminSubmissionController::class, 'previewAiPayload'])
        ->name('admin.submissions.checkAIPreview');
    Route::post('/submissions/{submission}/check-ai', [AdminSubmissionController::class, 'checkWithAI'])
        ->name('admin.submissions.checkAI');
    Route::post('/submissions/{submission}/preprocess/start', [AdminSubmissionController::class, 'startPreprocessing'])
        ->name('admin.submissions.startPreprocessing');
    Route::post('/submissions/{submission}/clean/start', [AdminSubmissionController::class, 'startCleaning'])
        ->name('admin.submissions.startCleaning');
    Route::post('/submissions/{submission}/structure/start', [AdminSubmissionController::class, 'startStructureDetection'])
        ->name('admin.submissions.startStructureDetection');
    Route::post('/submissions/{submission}/semantic/start', [AdminSubmissionController::class, 'startSemanticEnrichment'])
        ->name('admin.submissions.startSemanticEnrichment');
    Route::post('/submissions/{submission}/rubric/start', [AdminSubmissionController::class, 'startRubricPreparation'])
        ->name('admin.submissions.startRubricPreparation');
    Route::post('/submissions/{submission}/evaluation/start', [AdminSubmissionController::class, 'startAiEvaluation'])
        ->name('admin.submissions.startAiEvaluation');
    Route::post('/submissions/{submission}/evaluation/rerun', [AdminSubmissionController::class, 'rerunAiEvaluation'])
        ->name('admin.submissions.rerunAiEvaluation');
    Route::post('/submissions/{submission}/post-eval/start', [AdminSubmissionController::class, 'startPostEvaluationValidation'])
        ->name('admin.submissions.startPostEvaluationValidation');
    Route::post('/submissions/{submission}/presentation/start', [AdminSubmissionController::class, 'startResultPresentation'])
        ->name('admin.submissions.startResultPresentation');
    Route::post('/admin/quests/optional/generate-preview', [AdminOptionalQuestAiController::class, 'generatePreview'])
        ->name('admin.quests.optional.generate-preview');
    Route::post('/admin/quests/optional/commit-draft', [AdminOptionalQuestAiController::class, 'commitDraft'])
        ->name('admin.quests.optional.commit-draft');
    Route::post('/admin/quests/optional/theme-preview', [AdminOptionalQuestAiController::class, 'generateThemePreview'])
        ->name('admin.quests.optional.theme-preview');
    Route::post('/admin/quests/optional/commit-theme', [AdminOptionalQuestAiController::class, 'commitThemeBundle'])
        ->name('admin.quests.optional.commit-theme');

    Route::prefix('admin/task-banks')->name('admin.task-banks.')->group(function () {
        Route::get('/', [AdminTaskBankController::class, 'index'])->name('index');
        Route::post('/', [AdminTaskBankController::class, 'store'])->name('store');
        Route::put('/{taskBank:uuid}', [AdminTaskBankController::class, 'update'])->name('update');
        Route::delete('/{taskBank:uuid}', [AdminTaskBankController::class, 'destroy'])->name('destroy');

        Route::get('/{taskBank:uuid}/tasks', [AdminTaskBankController::class, 'show'])->name('show');
        Route::post('/{taskBank:uuid}/tasks', [AdminTaskBankController::class, 'storeQuestion'])->name('tasks.store');
        Route::post('/{taskBank:uuid}/tasks/import-json', [AdminTaskBankController::class, 'importQuestionsJson'])->name('tasks.import-json');
        Route::put('/{taskBank:uuid}/tasks/reorder', [AdminTaskBankController::class, 'reorderQuestions'])->name('tasks.reorder');
        Route::put('/{taskBank:uuid}/tasks/{question:uuid}/sequence', [AdminTaskBankController::class, 'moveQuestionSequence'])->name('tasks.sequence');
        Route::put('/{taskBank:uuid}/tasks/{question:uuid}', [AdminTaskBankController::class, 'updateQuestion'])->name('tasks.update');
        Route::delete('/{taskBank:uuid}/tasks/{question:uuid}', [AdminTaskBankController::class, 'destroyQuestion'])->name('tasks.destroy');
    });

    Route::prefix('admin/events')->name('admin.events.')->group(function () {
        Route::get('/', [AdminEventController::class, 'index'])->name('index');
        Route::post('/', [AdminEventController::class, 'store'])->name('store');
        Route::put('/{event:uuid}', [AdminEventController::class, 'update'])->name('update');
        Route::patch('/{uuid}/restore', [AdminEventController::class, 'restore'])->name('restore');
        Route::delete('/{uuid}/force', [AdminEventController::class, 'forceDestroy'])->name('force-destroy');
        Route::delete('/{event:uuid}', [AdminEventController::class, 'destroy'])->name('destroy');

        Route::get('/{event:uuid}', [AdminEventController::class, 'detail'])->name('detail');
        Route::get('/{event:uuid}/attendance', [AdminEventController::class, 'attendance'])->name('attendance');
        Route::post('/{event:uuid}/attendance/check-in-code', [AdminEventController::class, 'generateCheckInCode'])->name('attendance.check-in-code.generate');
        Route::post('/{event:uuid}/guides/attach', [AdminEventController::class, 'attachGuides'])->name('guides.attach');
        Route::post('/{event:uuid}/quests/attach', [AdminEventController::class, 'attachQuests'])->name('quests.attach');
        Route::delete('/{event:uuid}/guides/{guide}', [AdminEventController::class, 'detachGuide'])->name('guides.detach');
        Route::delete('/{event:uuid}/quests/{quest}', [AdminEventController::class, 'detachQuest'])->name('quests.detach');
        Route::patch('/{event:uuid}/guides/reorder', [AdminEventController::class, 'reorderGuides'])->name('guides.reorder');
        Route::patch('/{event:uuid}/quests/reorder', [AdminEventController::class, 'reorderQuests'])->name('quests.reorder');
        Route::patch('/{event:uuid}/attendance', [AdminEventController::class, 'updateAttendance'])->name('attendance.update');
    });

    Route::prefix('admin/rubrics')->name('admin.rubrics.')->group(function () {
        Route::get('/', [RubricController::class, 'index'])->name('index');
        Route::get('/create', [RubricController::class, 'create'])->name('create');
        Route::post('/', [RubricController::class, 'store'])->name('store');
        Route::post('/import-json', [RubricController::class, 'importJson'])->name('import-json');
        Route::get('/{rubric}', [RubricController::class, 'show'])->name('show');
        Route::get('/{rubric}/edit', [RubricController::class, 'edit'])->name('edit');
        Route::put('/{rubric}', [RubricController::class, 'update'])->name('update');
        Route::delete('/{rubric}', [RubricController::class, 'destroy'])->name('destroy');
        Route::get('/{rubric}/export', [RubricController::class, 'export'])->name('export');

        Route::post('/{rubric}/criteria', [RubricCriteriaController::class, 'store'])->name('criteria.store');
        Route::patch('/{rubric}/criteria/{criterion}', [RubricCriteriaController::class, 'update'])->name('criteria.update');
        Route::delete('/{rubric}/criteria/{criterion}', [RubricCriteriaController::class, 'destroy'])->name('criteria.destroy');

        Route::post('/{rubric}/levels', [RubricLevelController::class, 'store'])->name('levels.store');
        Route::patch('/{rubric}/levels/{level}', [RubricLevelController::class, 'update'])->name('levels.update');
        Route::delete('/{rubric}/levels/{level}', [RubricLevelController::class, 'destroy'])->name('levels.destroy');
    });

    Route::post('/admin/notifications/announcement', [NotificationDispatchController::class, 'announcement'])
        ->name('admin.notifications.announcement');

    Route::prefix('admin/creations')->name('admin.creations.')->group(function () {
        Route::get('/review-queue', [AdminCreationReviewController::class, 'index'])->name('queue');
        Route::get('/{creation}/preview', [AdminCreationReviewController::class, 'preview'])->name('preview');
        Route::post('/{creation}/review', [AdminCreationReviewController::class, 'submitFinalReview'])->name('review.submit');
        Route::post('/{creation}/reviews/publish-aggregate', [AdminCreationReviewController::class, 'publishOfficialAggregate'])
            ->name('review.publish-aggregate');
        Route::post('/{creation}/peer-reviews/{peerReview}/publish', [AdminCreationReviewController::class, 'publishOfficialReview'])
            ->name('review.publish');
    });

});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::get('/{user}/ledger', [AdminUserController::class, 'ledger'])->name('ledger');
        Route::post('/{user}/gold-adjustment', [AdminUserController::class, 'adjustGold'])->name('gold-adjustment');
        Route::patch('/{userId}/restore', [AdminUserController::class, 'restore'])->name('restore');
        Route::delete('/{userId}/force', [AdminUserController::class, 'forceDestroy'])->name('force-destroy');
        Route::patch('/{user}', [AdminUserController::class, 'update'])->name('update');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])->name('role.update');
        Route::patch('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('password.reset');
    });

    Route::prefix('admin/jobs')->name('admin.jobs.')->group(function () {
        Route::get('/', [AdminJobRoleController::class, 'index'])->name('index');
        Route::post('/', [AdminJobRoleController::class, 'store'])->name('store');
        Route::get('/{jobRole}', [AdminJobRoleController::class, 'show'])->name('show');
        Route::put('/{jobRole}', [AdminJobRoleController::class, 'update'])->name('update');
        Route::delete('/{jobRole}', [AdminJobRoleController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/shop-items')->name('admin.shop-items.')->group(function () {
        Route::get('/', [AdminShopItemController::class, 'index'])->name('index');
        Route::get('/{item}/detail', [AdminShopItemController::class, 'detail'])->name('detail');
        Route::post('/{item}/transactions/{transaction}/cancel', [AdminShopItemController::class, 'cancelTransaction'])->name('transactions.cancel');
        Route::post('/', [AdminShopItemController::class, 'store'])->name('store');
        Route::put('/{item}', [AdminShopItemController::class, 'update'])->name('update');
        Route::delete('/{item}', [AdminShopItemController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/profile-skins')->name('admin.profile-skins.')->group(function () {
        Route::get('/', [AdminProfileSkinController::class, 'index'])->name('index');
        Route::post('/', [AdminProfileSkinController::class, 'store'])->name('store');
        Route::post('/import-bundle', [AdminProfileSkinController::class, 'importBundle'])->name('import-bundle');
        Route::put('/{skin}', [AdminProfileSkinController::class, 'update'])->name('update');
        Route::delete('/{skin}', [AdminProfileSkinController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/daily-quest-definitions')->name('admin.daily-quest-definitions.')->group(function () {
        Route::get('/', [AdminDailyQuestDefinitionController::class, 'index'])->name('index');
        Route::post('/', [AdminDailyQuestDefinitionController::class, 'store'])->name('store');
        Route::put('/{definition}', [AdminDailyQuestDefinitionController::class, 'update'])->name('update');
        Route::delete('/{definition}', [AdminDailyQuestDefinitionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/submissions')->name('admin.submissions.manage.')->group(function () {
        Route::get('/', [AdminSubmissionManagementController::class, 'index'])->name('index');
        Route::patch('/{submission}', [AdminSubmissionManagementController::class, 'update'])->name('update');
        Route::patch('/{uuid}/restore', [AdminSubmissionManagementController::class, 'restore'])->name('restore');
        Route::delete('/{uuid}/force', [AdminSubmissionManagementController::class, 'forceDestroy'])->name('force-destroy');
        Route::delete('/{submission}', [AdminSubmissionManagementController::class, 'destroy'])->name('destroy');
    });

    Route::get('/admin/error-logs', [AdminErrorLogController::class, 'index'])->name('admin.error-logs.index');

    Route::patch('/admin/creations/{creation}/assignment', [AdminCreationReviewController::class, 'updateAssignment'])
        ->name('admin.creations.assignment.update');
});

Route::middleware(['auth', 'verified', 'role:admin,mentor'])->prefix('admin')->group(function () {

    // Halaman Utama CRUD (List & Form Jadi Satu)
    Route::get('/materi', [AdminGuideController::class, 'index'])->name('materi.index');

    // Proses Simpan Data Baru
    Route::post('/materi', [AdminGuideController::class, 'store'])->name('materi.store');

    // Proses Update (Gunakan POST + Spoofing PATCH di Vue agar upload file aman)
    Route::post('/materi/{uuid}', [AdminGuideController::class, 'update'])->name('materi.update');
    Route::patch('/materi/{uuid}/restore', [AdminGuideController::class, 'restore'])->name('materi.restore');
    Route::delete('/materi/{uuid}/force', [AdminGuideController::class, 'forceDestroy'])->name('materi.force-destroy');

    // Proses Hapus
    Route::delete('/materi/{uuid}', [AdminGuideController::class, 'destroy'])->name('materi.destroy');
});

Route::middleware(['auth', 'verified', 'role:admin,mentor'])->group(function () {

    // --- ADMIN AREA ---
    Route::get('/admin/study-groups/index', [AdminStudyGroupController::class, 'manage'])->name('groups.manage');
    Route::get('/admin/study-groups/{uuid}/user-preview', [StudyGroupController::class, 'staffPreview'])->name('groups.user-preview');
    Route::get('/admin/study-groups/{uuid}/attendance', [AdminStudyGroupController::class, 'attendanceDashboard'])->name('groups.attendance');
    Route::get('/admin/study-groups/{uuid}/join-requests', [AdminStudyGroupController::class, 'joinRequests'])->name('groups.join-requests');
    Route::get('/admin/study-groups/{uuid}/roadmaps', [AdminStudyGroupController::class, 'roadmaps'])->name('groups.roadmaps');
    Route::get('/admin/study-groups/{uuid}/students/{userId}', [AdminStudyGroupController::class, 'studentDetail'])->name('groups.students.detail');
    Route::get('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'detail'])->name('groups.detail');
    Route::get('/admin/study-groups/{groupUuid}/quests', [QuestController::class, 'index'])->name('groups.quests.index');
    Route::get('/admin/study-groups/{groupUuid}/guides', [AdminGuideController::class, 'index'])->name('groups.guides.index');
    Route::get('/admin/study-groups/{groupUuid}/events', [AdminEventController::class, 'index'])->name('groups.events.index');
    Route::get('/admin/quests/{quest}/user-preview', [QuestController::class, 'userPreview'])->name('quests.user-preview');
    Route::post('/admin/quests/{quest}/user-preview/submissions', [QuestController::class, 'previewSubmission'])->name('quests.user-preview.submissions');
    Route::get('/admin/guides/{guide:uuid}/user-preview', [GuideController::class, 'userPreview'])->name('guides.user-preview');
    Route::get('/admin/events/{event:uuid}/user-preview', [UserEventController::class, 'userPreview'])->name('events.user-preview');
    Route::post('/admin/study-groups/{uuid}/staff', [AdminStudyGroupController::class, 'assignStaff'])->name('groups.staff.assign');
    Route::delete('/admin/study-groups/{uuid}/staff/{userId}', [AdminStudyGroupController::class, 'removeStaff'])->name('groups.staff.remove');
    Route::get('/admin/study-groups/{uuid}/export-recap', [AdminStudyGroupController::class, 'exportRecap'])->name('groups.export-recap');
    Route::post('/admin/study-groups', [AdminStudyGroupController::class, 'store'])->name('groups.store');
    Route::put('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'update'])->name('groups.update');
    Route::post('/admin/study-groups/{uuid}/requests/{requestId}/approve', [AdminStudyGroupController::class, 'approveRequest'])
        ->name('groups.requests.approve');
    Route::post('/admin/study-groups/{uuid}/requests/{requestId}/reject', [AdminStudyGroupController::class, 'rejectRequest'])
        ->name('groups.requests.reject');
    Route::post('/admin/study-groups/{uuid}/roadmaps', [AdminStudyGroupController::class, 'attachRoadmap'])
        ->name('groups.roadmaps.attach');
    Route::delete('/admin/study-groups/{uuid}/roadmaps/{roadmapUuid}', [AdminStudyGroupController::class, 'detachRoadmap'])
        ->name('groups.roadmaps.detach');
    Route::delete('/admin/study-groups/{uuid}/members/{userId}', [AdminStudyGroupController::class, 'removeMember'])
        ->name('groups.members.remove');
    Route::patch('/admin/study-groups/{uuid}/restore', [AdminStudyGroupController::class, 'restore'])->name('groups.restore');
    Route::delete('/admin/study-groups/{uuid}/force', [AdminStudyGroupController::class, 'forceDestroy'])->name('groups.force-destroy');
    Route::delete('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'destroy'])->name('groups.destroy');
});

require __DIR__.'/auth.php';
