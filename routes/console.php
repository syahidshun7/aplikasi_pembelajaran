<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Validasi deadline quest: jalankan tiap 30 menit
Schedule::command('quests:close-expired')->everyThirtyMinutes();
Schedule::command('notifications:send-assignment-reminders')->hourly();
Schedule::command('notifications:send-dooplab-deadline-reminders')->hourly();
Schedule::command('daily-quests:generate')->dailyAt('00:00');
Schedule::command('daily-quests:expire')->everyThirtyMinutes();
Schedule::command('exams:finalize-expired')
    ->everyMinute()
    ->withoutOverlapping(5);
// Schedule::command('ai:optional-quests:generate-drafts --max-drafts=3')->weeklyOn(1, '01:30'); // dinonaktifkan - hasil tidak sesuai kebutuhan mentor/admin
