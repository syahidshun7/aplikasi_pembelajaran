<?php

use App\Http\Controllers\ProfileController;
// use Illuminate\Foundation\Application;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\AdminQuestController;
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
});



Route::middleware(['auth', 'verified', 'admin'])->group(function () {
   Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard'); // Beri nama agar mudah dipanggil

    Route::get('/quests', [QuestController::class, 'index'])->name('quests.index');
    Route::post('/quests', [QuestController::class, 'store'])->name('quests.store');
    Route::patch('/quests/{quest}', [QuestController::class, 'update'])->name('quests.update');
    Route::delete('/quests/{quest}', [QuestController::class, 'destroy'])->name('quests.destroy');

    // Pastikan route ini berada di dalam group admin kamu
    Route::get('/admin/quests/{quest}/submissions', [AdminQuestController::class, 'submissions'])
        ->name('admin.quests.submissions');
    Route::get('/submissions/{submission}/inspect', [AdminQuestController::class, 'inspect'])
        ->name('admin.submissions.inspect');
    Route::post('/submissions/{submission}/verdict', [AdminQuestController::class, 'verdict'])
        ->name('admin.submissions.verdict');
    Route::post('/submissions/{submission}/check-ai', [AdminQuestController::class, 'checkWithAI'])
    ->name('admin.submissions.checkAI');
});






require __DIR__ . '/auth.php';
