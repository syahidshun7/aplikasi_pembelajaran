<?php

namespace Tests\Unit;

use App\Models\Quest;
use App\Models\Submission;
use PHPUnit\Framework\TestCase;

class QuestAttemptPolicyTest extends TestCase
{
    public function test_single_attempt_quest_cannot_be_retried(): void
    {
        $quest = new Quest(['attempt_mode' => Quest::ATTEMPT_SINGLE]);

        $this->assertFalse($quest->allowsAnotherAttempt(1, $this->evaluatedSubmission(Submission::STATUS_REJECTED)));
    }

    public function test_limited_quest_stops_at_configured_limit(): void
    {
        $quest = new Quest([
            'attempt_mode' => Quest::ATTEMPT_LIMITED,
            'max_attempts' => 3,
        ]);
        $submission = $this->evaluatedSubmission(Submission::STATUS_REJECTED);

        $this->assertTrue($quest->allowsAnotherAttempt(2, $submission));
        $this->assertFalse($quest->allowsAnotherAttempt(3, $submission));
        $this->assertSame(2, $quest->remainingAttempts(1));
        $this->assertSame(0, $quest->remainingAttempts(3));
    }

    public function test_unlimited_quest_allows_another_rejected_attempt(): void
    {
        $quest = new Quest(['attempt_mode' => Quest::ATTEMPT_UNLIMITED]);

        $this->assertTrue($quest->allowsAnotherAttempt(25, $this->evaluatedSubmission(Submission::STATUS_REJECTED)));
        $this->assertNull($quest->remainingAttempts(25));
    }

    public function test_approved_attempt_can_be_retried_when_attempt_limit_allows_it(): void
    {
        $quest = new Quest([
            'attempt_mode' => Quest::ATTEMPT_LIMITED,
            'max_attempts' => 2,
        ]);
        $submission = $this->evaluatedSubmission(Submission::STATUS_APPROVED);

        $this->assertTrue($quest->allowsAnotherAttempt(1, $submission));
        $this->assertFalse($quest->allowsAnotherAttempt(2, $submission));
    }

    private function evaluatedSubmission(string $status): Submission
    {
        return new Submission(['status' => $status]);
    }
}
