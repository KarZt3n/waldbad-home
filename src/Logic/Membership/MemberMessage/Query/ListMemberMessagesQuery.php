<?php

namespace App\Logic\Membership\MemberMessage\Query;

use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\MemberMessage\Dto\MemberMessageResponse;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;

readonly class ListMemberMessagesQuery
{
    public function __construct(
        private MemberMessageManagerInterface $manager,
        private MemberManagerInterface $members,
    ) {
    }

    /**
     * @return list<MemberMessageResponse>
     */
    public function execute(): array
    {
        $emails = [];

        return array_map(function (MemberMessage $message) use (&$emails): MemberMessageResponse {
            if (!array_key_exists($message->memberId, $emails)) {
                $emails[$message->memberId] = $this->memberEmail($message->memberId);
            }

            return MemberMessageResponse::fromMessage($message, $emails[$message->memberId]);
        }, $this->manager->all());
    }

    /**
     * Eigene Adresse des Mitglieds, sonst die erste im Haushalt hinterlegte — Familienangehörige
     * haben häufig keine eigene E-Mail-Adresse.
     */
    private function memberEmail(string $memberId): ?string
    {
        try {
            $member = $this->members->get($memberId);
        } catch (MemberNotFoundException) {
            return null;
        }
        if ($member->email !== null && trim($member->email) !== '') {
            return $member->email;
        }
        foreach ($this->members->findByPrimaryMemberNumber($member->primaryMemberNumber) as $householdMember) {
            if ($householdMember->email !== null && trim($householdMember->email) !== '') {
                return $householdMember->email;
            }
        }

        return null;
    }
}
