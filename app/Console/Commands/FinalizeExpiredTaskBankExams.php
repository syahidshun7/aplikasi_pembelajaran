<?php

namespace App\Console\Commands;

use App\Http\Controllers\SubmissionController;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuestAttemptSession;
use App\Services\LmsNotificationService;
use App\Services\QuestAttemptNumberService;
use App\Services\TaskBankExamSessionService;
use App\Services\UserRewardSyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class FinalizeExpiredTaskBankExams extends Command
{
    protected $signature = 'exams:finalize-expired {--limit=200}';

    protected $description = 'Finalize expired non-game task bank attempts from their latest server draft.';

    public function handle(): int
    {
        $processed = 0;
        $failed = 0;

        UserQuestAttemptSession::query()
            ->whereNull('submitted_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (UserQuestAttemptSession $session) use (&$processed, &$failed): void {
                try {
                    $this->finalize($session);
                    $processed++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                    $this->error("Session {$session->id}: {$exception->getMessage()}");
                } finally {
                    Auth::logout();
                }
            });

        $this->info("Finalized: {$processed}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function finalize(UserQuestAttemptSession $session): void
    {
        $user = User::query()->findOrFail($session->user_id);
        $quest = Quest::query()->findOrFail($session->quest_id);
        $examSessions = app(TaskBankExamSessionService::class);

        if (! $examSessions->supports($quest)) {
            $session->update(['submitted_at' => now()]);
            return;
        }

        Auth::onceUsingId((int) $user->id);

        $request = Request::create('/internal/exam-timeout', 'POST', [
            'task_answers' => $session->draft_answers ?? [],
            'content' => (string) ($session->draft_content ?? ''),
            'requested_attempt_number' => (int) $session->attempt_number,
            'new_attempt' => (int) $session->attempt_number > 1,
            'client_submission_token' => (string) $session->submission_token,
            'timed_out' => true,
        ]);
        $request->setUserResolver(fn () => $user);

        app(SubmissionController::class)->store(
            $request,
            $quest,
            app(LmsNotificationService::class),
            app(UserRewardSyncService::class),
            app(QuestAttemptNumberService::class),
            $examSessions,
        );
    }
}
