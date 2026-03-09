<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\AdminStudyGroupController;
use App\Http\Controllers\AdminQuestController;
use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\AdminGuideController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminSubmissionManagementController;
use App\Http\Controllers\AdminJobRoleController;
use App\Http\Controllers\AdminEventController;
use App\Http\Controllers\AdminShopItemController;
use App\Http\Controllers\AdminTaskBankController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserEventController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('lobby');
Route::get('/landing', [HomeController::class, 'landing'])->name('landing');



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/quests/{quest}', [QuestController::class, 'show'])->name('quests.show');
    Route::post('/quests/{quest}/unlock-late', [QuestController::class, 'unlockLate'])->name('quests.unlock-late');
    Route::post('/quests/{quest}/submissions', [SubmissionController::class, 'store'])
        ->middleware('verified')
        ->name('submissions.store');
    Route::get('/submissions/{submission}', [SubmissionController::class, 'showSubmission'])
        ->name('submissions.show');
    Route::put('/submissions/{uuid}', [SubmissionController::class, 'update'])
        ->middleware('verified')
        ->name('submissions.update');
    Route::get('/quests-user', [QuestController::class, 'userIndex'])->name('quests.user.index');

    // --- USER AREA ---
    Route::get('/study-groups', [StudyGroupController::class, 'index'])->name('groups.index');
    Route::post('/study-groups/join', [StudyGroupController::class, 'join'])->name('groups.join');
    Route::post('/study-groups/{uuid}/leave', [StudyGroupController::class, 'leave'])->name('groups.leave');
    Route::get('/guides', [GuideController::class, 'userIndex'])->name('guides.user.index');
    Route::get('/guides/{guide}', [GuideController::class, 'userShow'])->name('guides.user.show');
    Route::get('/events', [UserEventController::class, 'index'])->name('events.user.index');
    Route::get('/events/{event:uuid}', [UserEventController::class, 'show'])->name('events.show');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
    Route::post('/shop/items/{item}/purchase', [ShopController::class, 'purchase'])
        ->middleware('verified')
        ->name('shop.purchase');
});



Route::middleware(['auth', 'verified', 'role:admin,mentor'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/dashboard', function () {
        return redirect()->route('dashboard');
    })->name('admin.dashboard');

    Route::get('/quests', [QuestController::class, 'index'])->name('quests.index');
    Route::post('/quests', [QuestController::class, 'store'])->name('quests.store');
    Route::patch('/quests/{quest}', [QuestController::class, 'update'])->name('quests.update');
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
    Route::post('/submissions/{submission}/check-ai', [AdminSubmissionController::class, 'checkWithAI'])
        ->name('admin.submissions.checkAI');

    Route::prefix('admin/task-banks')->name('admin.task-banks.')->group(function () {
        Route::get('/', [AdminTaskBankController::class, 'index'])->name('index');
        Route::post('/', [AdminTaskBankController::class, 'store'])->name('store');
        Route::put('/{taskBank:uuid}', [AdminTaskBankController::class, 'update'])->name('update');
        Route::delete('/{taskBank:uuid}', [AdminTaskBankController::class, 'destroy'])->name('destroy');

        Route::get('/{taskBank:uuid}/tasks', [AdminTaskBankController::class, 'show'])->name('show');
        Route::post('/{taskBank:uuid}/tasks', [AdminTaskBankController::class, 'storeQuestion'])->name('tasks.store');
        Route::put('/{taskBank:uuid}/tasks/{question:uuid}', [AdminTaskBankController::class, 'updateQuestion'])->name('tasks.update');
        Route::delete('/{taskBank:uuid}/tasks/{question:uuid}', [AdminTaskBankController::class, 'destroyQuestion'])->name('tasks.destroy');
    });

    Route::prefix('admin/events')->name('admin.events.')->group(function () {
        Route::get('/', [AdminEventController::class, 'index'])->name('index');
        Route::post('/', [AdminEventController::class, 'store'])->name('store');
        Route::put('/{event:uuid}', [AdminEventController::class, 'update'])->name('update');
        Route::delete('/{event:uuid}', [AdminEventController::class, 'destroy'])->name('destroy');

        Route::get('/{event:uuid}', [AdminEventController::class, 'detail'])->name('detail');
        Route::get('/{event:uuid}/attendance', [AdminEventController::class, 'attendance'])->name('attendance');
        Route::post('/{event:uuid}/guides/attach', [AdminEventController::class, 'attachGuides'])->name('guides.attach');
        Route::post('/{event:uuid}/quests/attach', [AdminEventController::class, 'attachQuests'])->name('quests.attach');
        Route::delete('/{event:uuid}/guides/{guide}', [AdminEventController::class, 'detachGuide'])->name('guides.detach');
        Route::delete('/{event:uuid}/quests/{quest}', [AdminEventController::class, 'detachQuest'])->name('quests.detach');
        Route::patch('/{event:uuid}/guides/reorder', [AdminEventController::class, 'reorderGuides'])->name('guides.reorder');
        Route::patch('/{event:uuid}/quests/reorder', [AdminEventController::class, 'reorderQuests'])->name('quests.reorder');
        Route::patch('/{event:uuid}/attendance', [AdminEventController::class, 'updateAttendance'])->name('attendance.update');
    });

});


Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::patch('/{user}', [AdminUserController::class, 'update'])->name('update');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])->name('role.update');
        Route::patch('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('password.reset');
    });

    Route::prefix('admin/jobs')->name('admin.jobs.')->group(function () {
        Route::get('/', [AdminJobRoleController::class, 'index'])->name('index');
        Route::post('/', [AdminJobRoleController::class, 'store'])->name('store');
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

    Route::prefix('admin/submissions')->name('admin.submissions.manage.')->group(function () {
        Route::get('/', [AdminSubmissionManagementController::class, 'index'])->name('index');
        Route::patch('/{submission}', [AdminSubmissionManagementController::class, 'update'])->name('update');
        Route::delete('/{submission}', [AdminSubmissionManagementController::class, 'destroy'])->name('destroy');
    });
});


Route::middleware(['auth', 'verified', 'role:admin,mentor'])->prefix('admin')->group(function () {

    // Halaman Utama CRUD (List & Form Jadi Satu)
    Route::get('/materi', [AdminGuideController::class, 'index'])->name('materi.index');

    // Proses Simpan Data Baru
    Route::post('/materi', [AdminGuideController::class, 'store'])->name('materi.store');

    // Proses Update (Gunakan POST + Spoofing PATCH di Vue agar upload file aman)
    Route::post('/materi/{uuid}', [AdminGuideController::class, 'update'])->name('materi.update');

    // Proses Hapus
    Route::delete('/materi/{uuid}', [AdminGuideController::class, 'destroy'])->name('materi.destroy');
});


Route::middleware(['auth', 'verified', 'admin'])->group(function () {


    // --- ADMIN AREA ---    
    Route::get('/admin/study-groups/index', [AdminStudyGroupController::class, 'manage'])->name('groups.manage');
    Route::get('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'detail'])->name('groups.detail');
    Route::post('/admin/study-groups', [AdminStudyGroupController::class, 'store'])->name('groups.store');
    Route::put('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'update'])->name('groups.update');
    Route::post('/admin/study-groups/{uuid}/requests/{requestId}/approve', [AdminStudyGroupController::class, 'approveRequest'])
        ->name('groups.requests.approve');
    Route::post('/admin/study-groups/{uuid}/requests/{requestId}/reject', [AdminStudyGroupController::class, 'rejectRequest'])
        ->name('groups.requests.reject');
    Route::delete('/admin/study-groups/{uuid}/members/{userId}', [AdminStudyGroupController::class, 'removeMember'])
        ->name('groups.members.remove');
    Route::delete('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'destroy'])->name('groups.destroy');
});

require __DIR__ . '/auth.php';
