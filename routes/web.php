<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified','admin'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});



Route::middleware(['auth', 'verified','admin'])->group(function () {
    Route::get('/quests', [QuestController::class, 'index'])->name('quests.index');
    Route::post('/quests', [QuestController::class, 'store'])->name('quests.store');
    Route::patch('/quests/{quest}', [QuestController::class, 'update'])->name('quests.update');
    Route::delete('/quests/{quest}', [QuestController::class, 'destroy'])->name('quests.destroy');
    Route::get('/quests/{quest}', [QuestController::class, 'show'])->name('quests.show');

});


Route::post('/quests/{quest}/submissions', [SubmissionController::class, 'store'])->name('submissions.store');



require __DIR__.'/auth.php';
