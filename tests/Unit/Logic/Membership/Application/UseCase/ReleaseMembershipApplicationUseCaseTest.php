<?php

namespace App\Tests\Unit\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Application\UseCase\ReleaseMembershipApplicationUseCase;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ReleaseMembershipApplicationUseCaseTest extends TestCase
{
    public function testReleasesFamilyApplicationWithHeadPartnerAndChild(): void
    {
        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $application = $this->familyApplication($now);

        $applications = $this->createMock(MembershipApplicationManagerInterface::class);
        $applications->expects(self::once())->method('get')->with('application-1')->willReturn($application);
        $applications->expects(self::once())->method('save')->willReturnCallback(
            static fn (MembershipApplication $saved): MembershipApplication => $saved,
        );

        $createdMembers = [];
        $orchestrator = $this->createMock(MemberOnboardingOrchestrator::class);
        $orchestrator->expects(self::exactly(3))->method('createFromRequest')->willReturnCallback(
            function (CreateMemberRequest $request) use (&$createdMembers): Member {
                $member = $this->memberFromRequest($request, 'member-'.(count($createdMembers) + 1));
                $createdMembers[] = ['request' => $request, 'member' => $member];

                return $member;
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $householdRecalculator = $this->createStub(HouseholdContributionRecalculator::class);
        $memberManager = $this->createStub(MemberManagerInterface::class);
        $memberManager->method('findByPrimaryMemberNumber')->willReturnCallback(
            static fn (): array => array_column($createdMembers, 'member'),
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $response = (new ReleaseMembershipApplicationUseCase(
            $applications,
            $orchestrator,
            $clock,
            $this->notConfiguredMailer(),
            $householdRecalculator,
            $memberManager,
            $this->memberAccessLinkBuilder(),
            $logger,
        ))->execute('application-1');

        self::assertNotNull($response->releasedAt);
        self::assertIsArray($response->releasedMemberIds);
        self::assertCount(3, $response->releasedMemberIds);

        [$head, $partner, $child] = array_column($createdMembers, 'request');
        self::assertSame(FamilyRole::Head, $head->familyRole);
        self::assertNull($head->primaryMemberNumber);
        self::assertSame(PayerType::SelfPayer, $head->payerType);
        // Die im Antrag je Person erfasste Anrede wird übernommen, statt wie zuvor pauschal auf
        // „Divers“ zu defaulten.
        self::assertSame(Salutation::Ms, $head->salutation);

        self::assertSame(FamilyRole::Partner, $partner->familyRole);
        self::assertSame(PayerType::OtherMember, $partner->payerType);
        self::assertSame($createdMembers[0]['member']->id, $partner->payerMemberId);
        self::assertSame(Salutation::Mr, $partner->salutation);

        self::assertSame(FamilyRole::Child, $child->familyRole);
        self::assertSame(PayerType::OtherMember, $child->payerType);
        self::assertSame(Salutation::Diverse, $child->salutation);
    }

    public function testJoinDateIsTheReleaseDateAndRoleUsesTheContributionAge(): void
    {
        // Eingegangen im August, angenommen im September: Eintritt ist die Annahme. Die zweite
        // Person wird erst am 31.12. 21 — zählt laut Stichtag 31.12. aber schon als erwachsen.
        $submittedAt = new \DateTimeImmutable('2026-08-14T09:00:00+02:00');
        $now = new \DateTimeImmutable('2026-09-28T10:00:00+02:00');
        $application = $this->familyApplication($now, '2005-12-31', $submittedAt);

        $applications = $this->createStub(MembershipApplicationManagerInterface::class);
        $applications->method('get')->willReturn($application);
        $applications->method('save')->willReturnCallback(
            static fn (MembershipApplication $saved): MembershipApplication => $saved,
        );

        $createdMembers = [];
        $orchestrator = $this->createStub(MemberOnboardingOrchestrator::class);
        $orchestrator->method('createFromRequest')->willReturnCallback(
            function (CreateMemberRequest $request) use (&$createdMembers): Member {
                $member = $this->memberFromRequest($request, 'member-'.(count($createdMembers) + 1));
                $createdMembers[] = ['request' => $request, 'member' => $member];

                return $member;
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        (new ReleaseMembershipApplicationUseCase(
            $applications,
            $orchestrator,
            $clock,
            $this->notConfiguredMailer(),
            $this->createStub(HouseholdContributionRecalculator::class),
            $this->createStub(MemberManagerInterface::class),
            $this->memberAccessLinkBuilder(),
            $this->createStub(LoggerInterface::class),
        ))->execute('application-1');

        [$head, $partner] = array_column($createdMembers, 'request');
        self::assertEquals($now, $head->joinedAt);
        self::assertEquals($now, $partner->joinedAt);
        self::assertSame(FamilyRole::Partner, $partner->familyRole);
    }

    /**
     * Die Bestätigungsmail listet die angelegten Personen und verweist für die Beiträge auf „Meine
     * Mitgliedschaft“, statt selbst eine Beitragsübersicht zu enthalten.
     */
    public function testConfirmationEmailListsThePersonsAndLinksToMemberAccessInsteadOfContributions(): void
    {
        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $application = $this->familyApplication($now);

        $applications = $this->createStub(MembershipApplicationManagerInterface::class);
        $applications->method('get')->willReturn($application);
        $applications->method('save')->willReturnCallback(static fn (MembershipApplication $saved): MembershipApplication => $saved);

        $createdMembers = [];
        $orchestrator = $this->createStub(MemberOnboardingOrchestrator::class);
        $orchestrator->method('createFromRequest')->willReturnCallback(
            function (CreateMemberRequest $request) use (&$createdMembers): Member {
                $member = $this->memberFromRequest($request, 'member-'.(count($createdMembers) + 1))
                    ->withContribution(ContributionCategory::FamilyAdult, 5000, 1500);
                $createdMembers[] = $member;

                return $member;
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $memberManager = $this->createStub(MemberManagerInterface::class);
        $memberManager->method('findByPrimaryMemberNumber')->willReturnCallback(static fn (): array => $createdMembers);

        $capturedEmail = null;
        $mailerStub = $this->createStub(MailerInterface::class);
        $mailerStub->method('send')->willReturnCallback(function (Email $email) use (&$capturedEmail): void {
            $capturedEmail = $email;
        });

        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));
        $templateManager = $this->createStub(MailTemplateManagerInterface::class);
        $templateManager->method('resolve')->willReturnCallback(
            static fn (MailTemplateKey $key): MailTemplate => new MailTemplate($key, 'Betreff', $key->defaultBody()),
        );
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(null);
        $mailer = new NotificationMailer(
            $mailerStub,
            $emailSettingsManager,
            new MailTemplateRenderer($templateManager, new MailContentRenderer(), $signatures),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
            'from@example.test',
            'Verein',
        );

        (new ReleaseMembershipApplicationUseCase(
            $applications,
            $orchestrator,
            $clock,
            $mailer,
            $this->createStub(HouseholdContributionRecalculator::class),
            $memberManager,
            $this->memberAccessLinkBuilder(),
            $this->createStub(LoggerInterface::class),
        ))->execute('application-1');

        self::assertNotNull($capturedEmail);
        self::assertSame('maria@example.com', $capturedEmail->getTo()[0]->getAddress());
        $text = (string) $capturedEmail->getTextBody();
        self::assertStringContainsString('- Maria Muster (Hauptmitglied), geb. 01.01.1985 (Beitragsalter 2026: 41)', $text);
        self::assertStringContainsString('- Mia Muster (Kind), geb. 01.01.2015 (Beitragsalter 2026: 11)', $text);
        self::assertStringContainsString('„Meine Mitgliedschaft“', $text);
        self::assertStringContainsString('https://example.test/meine-mitgliedschaft', $text);
        self::assertStringContainsString('https://example.test/meine-mitgliedschaft', (string) $capturedEmail->getHtmlBody());
        self::assertStringNotContainsString('€', $text);
        self::assertStringNotContainsString('{{', $text);
    }

    public function testCannotReleaseTheSameApplicationTwice(): void
    {
        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $application = $this->familyApplication($now)->release(['member-1'], $now);

        $applications = $this->createMock(MembershipApplicationManagerInterface::class);
        $applications->expects(self::once())->method('get')->willReturn($application);
        $applications->expects(self::never())->method('save');

        $orchestrator = $this->createMock(MemberOnboardingOrchestrator::class);
        $orchestrator->expects(self::never())->method('createFromRequest');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $this->expectException(BusinessRuleViolationException::class);
        (new ReleaseMembershipApplicationUseCase(
            $applications,
            $orchestrator,
            $clock,
            $this->notConfiguredMailer(),
            $this->createStub(HouseholdContributionRecalculator::class),
            $this->createStub(MemberManagerInterface::class),
            $this->memberAccessLinkBuilder(),
            $this->createStub(LoggerInterface::class),
        ))->execute('application-1');
    }

    /**
     * Die Mitglieder wurden zu diesem Zeitpunkt bereits angelegt und der Antrag bereits als
     * freigegeben gespeichert — ein Fehler danach (hier: beim Neuberechnen des Haushalts für die
     * Bestätigungsmail) darf die Freigabe nicht als fehlgeschlagen erscheinen lassen.
     */
    public function testReleaseSucceedsEvenWhenPreparingTheConfirmationEmailFails(): void
    {
        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $application = $this->familyApplication($now);

        $applications = $this->createStub(MembershipApplicationManagerInterface::class);
        $applications->method('get')->willReturn($application);
        $applications->method('save')->willReturnCallback(static fn (MembershipApplication $saved): MembershipApplication => $saved);

        $createdMembers = [];
        $orchestrator = $this->createStub(MemberOnboardingOrchestrator::class);
        $orchestrator->method('createFromRequest')->willReturnCallback(
            function (CreateMemberRequest $request) use (&$createdMembers): Member {
                $member = $this->memberFromRequest($request, 'member-'.(count($createdMembers) + 1));
                $createdMembers[] = $member;

                return $member;
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $householdRecalculator = $this->createStub(HouseholdContributionRecalculator::class);
        $householdRecalculator->method('recalculate')->willThrowException(new BusinessRuleViolationException('Kein passender Beitragssatz.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $response = (new ReleaseMembershipApplicationUseCase(
            $applications,
            $orchestrator,
            $clock,
            $this->notConfiguredMailer(),
            $householdRecalculator,
            $this->createStub(MemberManagerInterface::class),
            $this->memberAccessLinkBuilder(),
            $logger,
        ))->execute('application-1');

        self::assertNotNull($response->releasedAt);
        self::assertIsArray($response->releasedMemberIds);
        self::assertCount(3, $response->releasedMemberIds);
    }

    private function familyApplication(\DateTimeImmutable $now, string $secondApplicantBirthDate = '1983-01-01', ?\DateTimeImmutable $submittedAt = null): MembershipApplication
    {
        return new MembershipApplication(
            id: 'application-1',
            membershipType: MembershipType::Family,
            applicants: [
                new Applicant('a-1', 0, Salutation::Ms, 'Maria', 'Muster', new \DateTimeImmutable('1985-01-01'), 'Kirchanger', '14', '14822', 'Borkheide', null, 'maria@example.com'),
                new Applicant('a-2', 1, Salutation::Mr, 'Max', 'Muster', new \DateTimeImmutable($secondApplicantBirthDate), 'Kirchanger', '14', '14822', 'Borkheide', null, null),
                new Applicant('a-3', 2, Salutation::Diverse, 'Mia', 'Muster', new \DateTimeImmutable('2015-01-01'), 'Kirchanger', '14', '14822', 'Borkheide', null, null),
            ],
            accountHolder: 'Maria Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            signerName: 'Maria Muster',
            emailConsent: true,
            declarationVersion: '2026-01',
            version: 1,
            submittedAt: $submittedAt ?? $now,
            updatedAt: $now,
        );
    }

    private function memberFromRequest(CreateMemberRequest $request, string $id): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'M-000'.substr($id, -1),
            primaryMemberNumber: $request->primaryMemberNumber ?? 'M-000'.substr($id, -1),
            salutation: Salutation::Diverse,
            lastName: $request->lastName,
            firstName: $request->firstName,
            birthDate: $request->birthDate,
            street: $request->street,
            postalCode: $request->postalCode,
            city: $request->city,
            email: $request->email,
            phone: $request->phone,
            familyRole: $request->familyRole,
            joinedAt: $request->joinedAt,
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: $request->accountHolder,
            iban: $request->iban,
            bankName: $request->bankName,
            mandateReference: 'M-000'.substr($id, -1),
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $request->payerType,
            payerMemberId: $request->payerMemberId,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }

    /**
     * Der eigentliche Mailversand ist für diese Tests irrelevant, es genügt ein Stub-`MailerInterface`.
     */
    private function notConfiguredMailer(): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));

        $renderer = $this->createStub(MailTemplateRenderer::class);
        $renderer->method('render')->willReturn(['subject' => 'Betreff', 'body' => 'Text', 'html' => '<p>Text</p>']);

        return new NotificationMailer(
            $this->createStub(MailerInterface::class),
            $emailSettingsManager,
            $renderer,
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
            'from@example.test',
            'Verein',
        );
    }

    private function memberAccessLinkBuilder(): MemberAccessLinkBuilderInterface
    {
        $linkBuilder = $this->createStub(MemberAccessLinkBuilderInterface::class);
        $linkBuilder->method('buildEntry')->willReturn('https://example.test/meine-mitgliedschaft');

        return $linkBuilder;
    }
}
