<?php

namespace App\Logic\Membership\Cancellation\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Cancellation\Dto\MemberCancellationResponse;
use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;

/**
 * Verwirft eine Kündigung als Ganzes, z. B. weil sie versehentlich abgeschickt oder zurückgezogen
 * wurde. Die Mitglieder bleiben unverändert, es geht keine Mail raus.
 */
readonly class RejectMemberCancellationUseCase
{
    public function __construct(
        private MemberCancellationManagerInterface $cancellations,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<MemberCancellationResponse>
     */
    public function execute(array $ids): array
    {
        $now = $this->clock->now();

        return array_map(
            fn (MemberCancellation $cancellation): MemberCancellationResponse => MemberCancellationResponse::fromCancellation(
                $this->cancellations->save($cancellation->decide(MemberCancellationStatus::Rejected, $now)),
            ),
            ConfirmMemberCancellationUseCase::load($this->cancellations, $ids),
        );
    }
}
