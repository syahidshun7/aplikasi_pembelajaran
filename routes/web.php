<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\AdminStudyGroupController;
use App\Http\Controllers\AdminQuestController;
use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\AdminGuideController;
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

    // --- USER AREA ---
    Route::get('/study-groups', [StudyGroupController::class, 'index'])->name('groups.index');
    Route::post('/study-groups/join', [StudyGroupController::class, 'join'])->name('groups.join');
    Route::post('/study-groups/{uuid}/leave', [StudyGroupController::class, 'leave'])->name('groups.leave');
});



Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard'); // Beri nama agar mudah dipanggil

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
});


Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {

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
    Route::post('/admin/study-groups', [AdminStudyGroupController::class, 'store'])->name('groups.store');
    Route::put('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'update'])->name('groups.update');
    Route::delete('/admin/study-groups/{uuid}', [AdminStudyGroupController::class, 'destroy'])->name('groups.destroy');
});

require __DIR__ . '/auth.php';
