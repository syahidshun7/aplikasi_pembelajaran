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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('lobby');



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/quests/{quest}', [QuestController::class, 'show'])->name('quests.show');
    Route::post('/quests/{quest}/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::get('/submissions/{submission}', [SubmissionController::class, 'showSubmission'])
        ->name('submissions.show');
    Route::put('/submissions/{uuid}', [SubmissionController::class, 'update'])
        ->name('submissions.update');
    Route::get('/quests-user', [QuestController::class, 'userIndex'])->name('quests.user.index');

    // --- USER AREA ---
    Route::get('/study-groups', [StudyGroupController::class, 'index'])->name('groups.index');
    Route::post('/study-groups/join', [StudyGroupController::class, 'join'])->name('groups.join');
    Route::post('/study-groups/{uuid}/leave', [StudyGroupController::class, 'leave'])->name('groups.leave');
    Route::get('/guides', [GuideController::class, 'userIndex'])->name('guides.user.index');
});



Route::middleware(['auth', 'verified', 'admin'])->group(function () {
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
    Route::get('/submissions/{submission}/inspect', [AdminSubmissionController::class, 'inspect'])
        ->name('admin.submissions.inspect');
    Route::post('/submissions/{submission}/verdict', [AdminSubmissionController::class, 'verdict'])
        ->name('admin.submissions.verdict');
    Route::post('/submissions/{submission}/check-ai', [AdminSubmissionController::class, 'checkWithAI'])
        ->name('admin.submissions.checkAI');

    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])->name('role.update');
        Route::patch('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('password.reset');
    });

    Route::prefix('admin/submissions')->name('admin.submissions.manage.')->group(function () {
        Route::get('/', [AdminSubmissionManagementController::class, 'index'])->name('index');
        Route::patch('/{submission}', [AdminSubmissionManagementController::class, 'update'])->name('update');
        Route::delete('/{submission}', [AdminSubmissionManagementController::class, 'destroy'])->name('destroy');
    });
});


Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {

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
