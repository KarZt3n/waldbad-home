<?php

namespace App\Logic\Membership\MemberMessage\Dto;

readonly class ReplyToMemberMessageRequest
{
    public function __construct(
        public string $messageId,
        public string $recipient,
        public string $subject,
        public string $body,
    ) {
    }
}
