<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Service;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestConfirmationMailer;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestRecipientResolver;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\Email\Service\NotificationMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class EventHelpRequestConfirmationMailerTest extends TestCase
{
    public function testSendsToEveryResolvedRecipientWithExpectedPlaceholders(): void
    {
        $member = $this->member();
        $resolver = $this->createStub(EventHelpRequestRecipientResolver::class);
        $resolver->method('resolve')->willReturn(['erika@example.test', 'andere-adresse@example.test']);
        $notificationMailer = $this->createMock(NotificationMailer::class);
        $sentTo = [];
        $notificationMailer->method('sendTo')->willReturnCallback(function (string $to, MailTemplateKey $key, array $placeholders) use (&$sentTo): void {
            self::assertSame(MailTemplateKey::EventHelpRequestConfirmation, $key);
            self::assertSame('Erika', $placeholders['vorname']);
            self::assertSame('Musterfrau', $placeholders['nachname']);
            self::assertSame('Frühjahrsputz', $placeholders['veranstaltung']);
            self::assertSame('13.06.2026', $placeholders['datum']);
            $sentTo[] = $to;
        });
        $mailer = new EventHelpRequestConfirmationMailer($resolver, $notificationMailer, new NullLogger());

        $mailer->send('Erika', 'Musterfrau', $this->event(), $member, 'andere-adresse@example.test');

        sort($sentTo);
        self::assertSame(['andere-adresse@example.test', 'erika@example.test'], $sentTo);
    }

    public function testSendsNothingWhenResolverFindsNoRecipient(): void
    {
        $resolver = $this->createStub(EventHelpRequestRecipientResolver::class);
        $resolver->method('resolve')->willReturn([]);
        $notificationMailer = $this->createMock(NotificationMailer::class);
        $notificationMailer->expects(self::never())->method('sendTo');
        $mailer = new EventHelpRequestConfirmationMailer($resolver, $notificationMailer, new NullLogger());

        $mailer->send('Helfer', 'Ohnematch', $this->event(), null, null);
    }

    public function testLogsAndDoesNotThrowWhenResolvingRecipientsFails(): void
    {
        $resolver = $this->createStub(EventHelpRequestRecipientResolver::class);
        $resolver->method('resolve')->willThrowException(new \RuntimeException('DB weg'));
        $notificationMailer = $this->createMock(NotificationMailer::class);
        $notificationMailer->expects(self::never())->method('sendTo');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $mailer = new EventHelpRequestConfirmationMailer($resolver, $notificationMailer, $logger);

        $mailer->send('Sally', 'Kuck', $this->event(), $this->member(), null);
    }

    private function event(): VolunteerEvent
    {
        return new VolunteerEvent(
            identifier: 'fruehjahrsputz-2026',
            title: 'Frühjahrsputz',
            date: '2026-06-13',
            time: '09:00',
            activities: [],
        );
    }

    private function member(): Member
    {
        return new Member(
            id: 'm1',
            memberNumber: 'Bad-m1',
            primaryMemberNumber: 'Bad-m1',
            salutation: Salutation::Mr,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-06-15'),
            street: 'Musterweg 1',
            postalCode: '14547',
            city: 'Borkheide',
            email: 'erika@example.test',
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
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
