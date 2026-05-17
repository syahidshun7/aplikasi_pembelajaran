<?php

namespace App\Events;

use App\Models\StudyGroupJoinRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JoinGroupRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly StudyGroupJoinRequest $joinRequest,
    ) {}
}

