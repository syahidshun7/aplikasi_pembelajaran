<?php

namespace App\Console\Commands;

use App\Models\DoopLabTodo;
use App\Notifications\DoopLabTodoDeadlineReminderNotification;
use Illuminate\Console\Command;

class SendDoopLabTodoDeadlineReminderNotifications extends Command
{
    protected $signature = 'notifications:send-dooplab-deadline-reminders';

    protected $description = 'Kirim pengingat deadline to-do DoopLab yang mendekati jatuh tempo';

    public function handle(): int
    {
        $now = now();
        $deadlineLimit = now()->addDay();

        $todos = DoopLabTodo::query()
            ->where('is_completed', false)
            ->whereNotNull('deadline')
            ->whereNull('deadline_reminded_at')
            ->whereBetween('deadline', [$now, $deadlineLimit])
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->with('owner:id,name,username,email')
            ->get();

        if ($todos->isEmpty()) {
            $this->info('DOOPLAB_DEADLINE_REMINDERS_SENT=0');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($todos as $todo) {
            if (! $todo->owner) {
                continue;
            }

            $todo->owner->notify(new DoopLabTodoDeadlineReminderNotification(
                $todo,
                (bool) $todo->notify_deadline_email
            ));

            $todo->forceFill([
                'deadline_reminded_at' => now(),
            ])->save();

            $sent++;
        }

        $this->info("DOOPLAB_DEADLINE_REMINDERS_SENT={$sent}");

        return self::SUCCESS;
    }
}

