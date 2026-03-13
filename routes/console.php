<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Validasi deadline quest: jalankan tiap 30 menit
Schedule::command('quests:close-expired')->everyThirtyMinutes();
