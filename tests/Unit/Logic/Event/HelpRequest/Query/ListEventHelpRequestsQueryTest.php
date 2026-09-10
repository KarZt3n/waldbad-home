<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Query;

use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Query\ListEventHelpRequestsQuery;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\MemberProviderInterface;
use PHPUnit\Framework\TestCase;

final class ListEventHelpRequestsQueryTest extends TestCase
{
    public function testUsesCurrentEventDataAndFallsBackToTheStoredSnapshot(): void
    {
        $updatedRequest = $this->request('updated-event', 'Alter Titel', '2026-08-15', '14:00');
        $removedRequest = $this->request('removed-event', 'Historischer Titel', '2025-07-12', '10:00');
        $manager = $this->createStub(EventHelpRequestManagerInterface::class);
        $manager->method('all')->willReturn([$updatedRequest, $removedRequest]);
        $eventProvider = $this->createMock(VolunteerEventProviderInterface::class);
        $eventProvider->expects(self::exactly(2))
            ->method('findCurrent')
            ->willReturnMap([
                ['updated-event', new VolunteerEvent('updated-event', 'Neuer Titel', '2026-09-05', '16:30', [])],
                ['removed-event', null],
            ]);
        $memberProvider = $this->createStub(MemberProviderInterface::class);

        $responses = (new ListEventHelpRequestsQuery($manager, $eventProvider, $memberProvider))->execute();

        self::assertSame('Neuer Titel', $responses[0]->eventTitle);
        self::assertSame('2026-09-05', $responses[0]->eventDate);
        self::assertSame('16:30', $responses[0]->eventTime);
        self::assertNull($responses[0]->memberNumber);
        self::assertSame('Historischer Titel', $responses[1]->eventTitle);
        self::assertSame('2025-07-12', $responses[1]->eventDate);
        self::assertSame('10:00', $responses[1]->eventTime);
    }

    public function testResolvesTheCurrentMemberNumberOfALinkedRequest(): void
    {
        $linkedRequest = $this->request('linked-event', 'Frühjahrsputz', '2026-08-15', '14:00', 'member-1');
        $manager = $this->createStub(EventHelpRequestManagerInterface::class);
        $manager->method('all')->willReturn([$linkedRequest]);
        $eventProvider = $this->createStub(VolunteerEventProviderInterface::class);
        $eventProvider->method('findCurrent')->willReturn(null);
        $memberProvider = $this->createMock(MemberProviderInterface::class);
        $memberProvider->expects(self::once())->method('find')->with('member-1')->willReturn($this->member('member-1', 'Bad-00042'));

        $responses = (new ListEventHelpRequestsQuery($manager, $eventProvider, $memberProvider))->execute();

        self::assertSame('Bad-00042', $responses[0]->memberNumber);
        self::assertSame('Erika', $responses[0]->memberFirstName);
        self::assertSame('Musterfrau', $responses[0]->memberLastName);
    }

    private function request(string $eventIdentifier, string $eventTitle, string $eventDate, string $eventTime, ?string $memberId = null): EventHelpRequest
    {
        $submittedAt = new \DateTimeImmutable('2026-08-01T12:00:00+02:00');

        return new EventHelpRequest(
            id: $eventIdentifier.'-request',
            eventIdentifier: $eventIdentifier,
            eventTitle: $eventTitle,
            eventDate: $eventDate,
            eventTime: $eventTime,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            message: '',
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            selectedActivities: [],
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
            memberId: $memberId,
        );
    }

    private function member(string $id, string $memberNumber): Member
    {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $memberNumber,
            salutation: \App\Logic\Membership\Member\Model\Salutation::Mr,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Musterweg 1',
            postalCode: '14547',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: \App\Logic\Membership\Member\Model\FamilyRole::Head,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: \App\Logic\Membership\Member\Model\MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: \App\Logic\Membership\Member\Model\PaymentMethod::BankTransfer,
            paymentInterval: \App\Logic\Membership\PaymentInterval::Yearly,
            paymentDay: \App\Logic\Membership\Member\Model\PaymentDay::First,
            payerType: \App\Logic\Membership\Member\Model\PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 1,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
        );
    }
}
