<?php

namespace App\Tests\Unit\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Common\AccessPasswordGeneratorInterface;
use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Membership\MemberAccess\Service\MemberPayerGroupResolver;
use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\MemberAccess\UseCase\RequestMemberAccessUseCase;
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

final class RequestMemberAccessUseCaseTest extends TestCase
{
    private const string PASSWORD = 'aB3x9';

    public function testDoesNothingWhenNoMemberUsesThatEmail(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([]);
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('unknown@example.test', '1990-01-01');
    }

    public function testDoesNothingForAnInvalidEmailFormat(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->expects(self::never())->method('findByEmail');

        (new RequestMemberAccessUseCase(
            $members,
            $this->createStub(MemberAccessTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('not-an-email', '1990-01-01');
    }

    public function testDoesNothingForAnUnparseableBirthDate(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->expects(self::never())->method('findByEmail');

        (new RequestMemberAccessUseCase(
            $members,
            $this->createStub(MemberAccessTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('erika@example.test', 'not-a-date');
    }

    /**
     * Die E-Mail-Adresse allein reicht nicht — sie identifiziert in der Regel einen ganzen
     * Haushalt, erst das zusätzlich passende Geburtsdatum grenzt auf die anfragende Person ein.
     */
    public function testDoesNothingWhenTheBirthDateDoesNotMatch(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([$this->member()]);
        $members->method('get')->willReturn($this->member());
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('erika@example.test', '1991-02-03');
    }

    /**
     * Ausgetretene Mitglieder ohne aktive Haushaltsmitglieder erhalten keinen Zugang: auch bei
     * passender E-Mail-Adresse und passendem Geburtsdatum wird weder ein Token gespeichert noch
     * eine Mail verschickt.
     */
    public function testDoesNothingWhenTheMatchingMemberHasLeftAndNobodyInTheHouseholdIsActive(): void
    {
        $leftMember = $this->member(leftAt: new \DateTimeImmutable('2025-12-31'));
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([$leftMember]);
        $members->method('get')->willReturn($leftMember);
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $clock,
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('erika@example.test', '1990-01-01');
    }

    /**
     * Kernstück: bei einer E-Mail-Adresse + Geburtsdatum, die zusammen zu einem Mitglied passen,
     * wird ein an dessen Haushalt (`primaryMemberNumber`) gebundener Token mit dem SHA-256-Hash des
     * (unveränderten) Klartext-Tokens gespeichert, der Ablauf liegt 30 Minuten in der Zukunft, und
     * die Mail enthält den vom `MemberAccessLinkBuilderInterface` gebauten Link.
     */
    public function testSavesAHashedTokenAndSendsTheLinkWhenAMemberUsesThatEmail(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([$this->member()]);
        $members->method('get')->willReturn($this->member());

        $tokenGenerator = $this->createStub(SecureTokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-token-value');

        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $savedToken = null;
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::once())->method('save')->willReturnCallback(
            function (MemberAccessToken $token) use (&$savedToken): MemberAccessToken {
                $savedToken = $token;

                return $token;
            },
        );

        $linkBuilder = $this->createStub(MemberAccessLinkBuilderInterface::class);
        $linkBuilder->method('build')->willReturn('https://example.test/meine-mitgliedschaft?token=raw-token-value');

        $capturedEmail = null;
        $mailer = $this->configuredMailer($capturedEmail);

        $passwordHasher = new MemberAccessPasswordHasher();

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $tokenGenerator,
            $this->passwordGenerator(),
            $passwordHasher,
            $this->identifierGenerator('token-id-1'),
            $clock,
            $mailer,
            $linkBuilder,
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('  Erika@Example.test  ', '1990-01-01');

        self::assertNotNull($savedToken);
        self::assertSame('erika@example.test', $savedToken->email);
        self::assertSame(hash('sha256', 'raw-token-value'), $savedToken->tokenHash);
        self::assertSame('Bad-001', $savedToken->primaryMemberNumber);
        self::assertTrue($passwordHasher->verify(self::PASSWORD, $savedToken->passwordHash));
        self::assertEquals($now->modify('+30 minutes'), $savedToken->expiresAt);

        self::assertNotNull($capturedEmail);
        self::assertStringContainsString('https://example.test/meine-mitgliedschaft?token=raw-token-value', (string) $capturedEmail->getTextBody());
        self::assertStringContainsString(self::PASSWORD, (string) $capturedEmail->getTextBody());
        self::assertStringContainsString('30 Minuten', (string) $capturedEmail->getTextBody());
        self::assertSame('express', $capturedEmail->getHeaders()->get('X-Transport')?->getBodyAsString());
    }

    public function testLogsInsteadOfThrowingWhenSomethingFails(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new RequestMemberAccessUseCase(
            $members,
            $this->createStub(MemberAccessTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $logger,
            new MemberPayerGroupResolver($members),
        ))->execute('erika@example.test', '1990-01-01');
    }

    /**
     * Ein ausgetretenes Elternteil verwaltet weiterhin die Mitgliedschaft der Kinder: Solange im
     * Haushalt noch jemand aktiv ist, bekommt es den Zugang für diesen Haushalt.
     */
    public function testGrantsAccessToALeftMemberWhileTheHouseholdStillHasActiveMembers(): void
    {
        $leftParent = $this->member(leftAt: new \DateTimeImmutable('2025-12-31'));
        $activeChild = $this->member(id: 'member-2', memberNumber: 'Bad-002', birthDate: new \DateTimeImmutable('2015-01-01'), payerMemberId: 'member-1');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([$leftParent]);
        $members->method('get')->willReturn($leftParent);
        $members->method('findByPayerMemberId')->willReturn([$activeChild]);
        $savedTokens = [];
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::once())->method('save')->willReturnCallback(static function (MemberAccessToken $token) use (&$savedTokens): MemberAccessToken {
            $savedTokens[] = $token;

            return $token;
        });
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));
        $capturedEmail = null;

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $clock,
            $this->configuredMailer($capturedEmail),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('erika@example.test', '1990-01-01');

        self::assertCount(1, $savedTokens);
        self::assertSame('member-1', $savedTokens[0]->payerMemberId);
        self::assertInstanceOf(Email::class, $capturedEmail);
    }

    /**
     * E-Mail-Adresse und Geburtsdatum müssen nicht zur selben Person gehören: Die Adresse eines
     * Elternteils mit dem Geburtsdatum eines Kindes aus demselben Haushalt reicht.
     */
    public function testAcceptsTheBirthDateOfAnotherHouseholdMember(): void
    {
        $parent = $this->member();
        $child = $this->member(id: 'member-2', memberNumber: 'Bad-002', birthDate: new \DateTimeImmutable('2015-04-05'), payerMemberId: 'member-1');
        $savedTokens = $this->requestAccess([$parent], [$parent, $child], '2015-04-05');

        self::assertCount(1, $savedTokens);
        self::assertSame('Bad-001', $savedTokens[0]->primaryMemberNumber);
        self::assertSame('member-1', $savedTokens[0]->payerMemberId);
    }

    /**
     * Ein Kind, für das das Elternteil zahlt, ruft den Zugang ebenfalls für dessen Zahlergruppe ab.
     */
    public function testBindsAccessOfAPaidForMemberToItsPayer(): void
    {
        $parent = $this->member();
        $child = $this->member(id: 'member-2', memberNumber: 'Bad-002', birthDate: new \DateTimeImmutable('2015-04-05'), payerMemberId: 'member-1');
        $savedTokens = $this->requestAccess([$child], [$parent, $child], '1990-01-01');

        self::assertCount(1, $savedTokens);
        self::assertSame('member-1', $savedTokens[0]->payerMemberId);
    }

    public function testRejectsABirthDateFromOutsideThePayerGroup(): void
    {
        $parent = $this->member();
        $child = $this->member(id: 'member-2', memberNumber: 'Bad-002', birthDate: new \DateTimeImmutable('2015-04-05'), payerMemberId: 'member-1');

        self::assertSame([], $this->requestAccess([$parent], [$parent, $child], '2016-04-05'));
    }

    /**
     * Die Partnerin im selben Haushalt zahlt selbst: Mit der gemeinsamen Adresse und ihrem
     * Geburtsdatum entsteht ein Zugang nur für sie — nicht für den anderen Zahler und seine Kinder.
     */
    public function testASelfPayingPartnerWithTheSameEmailGetsOnlyHerOwnGroup(): void
    {
        $father = $this->member();
        $child = $this->member(id: 'member-2', memberNumber: 'Bad-002', birthDate: new \DateTimeImmutable('2015-04-05'), payerMemberId: 'member-1');
        $partner = $this->member(id: 'member-3', memberNumber: 'Bad-003', birthDate: new \DateTimeImmutable('1988-08-08'));

        $savedTokens = $this->requestAccess([$father, $partner], [$father, $child, $partner], '1988-08-08');
        self::assertCount(1, $savedTokens);
        self::assertSame('member-3', $savedTokens[0]->payerMemberId);

        // Ihre Adresse mit dem Geburtsdatum eines Kindes, für das sie nicht zahlt, reicht nicht.
        self::assertSame([], $this->requestAccess([$partner], [$father, $child, $partner], '2015-04-05'));
    }

    /**
     * @param list<Member> $byEmail
     * @param list<Member> $all
     *
     * @return list<MemberAccessToken>
     */
    private function requestAccess(array $byEmail, array $all, string $birthDate): array
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn($byEmail);
        $members->method('get')->willReturnCallback(static function (string $id) use ($all): Member {
            foreach ($all as $member) {
                if ($member->id === $id) {
                    return $member;
                }
            }

            throw new MemberNotFoundException($id);
        });
        $members->method('findByPayerMemberId')->willReturnCallback(static fn (string $payerId): array => array_values(array_filter(
            $all,
            static fn (Member $member): bool => $member->payerMemberId === $payerId,
        )));
        $savedTokens = [];
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('save')->willReturnCallback(static function (MemberAccessToken $token) use (&$savedTokens): MemberAccessToken {
            $savedTokens[] = $token;

            return $token;
        });
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $clock,
            $this->noopMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
            new MemberPayerGroupResolver($members),
        ))->execute('erika@example.test', $birthDate);

        return $savedTokens;
    }

    private function member(
        ?\DateTimeImmutable $leftAt = null,
        string $id = 'member-1',
        string $memberNumber = 'Bad-001',
        ?\DateTimeImmutable $birthDate = null,
        ?string $payerMemberId = null,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: 'Bad-001',
            salutation: \App\Logic\Membership\Member\Model\Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: $birthDate ?? new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: 'erika@example.test',
            phone: null,
            familyRole: \App\Logic\Membership\Member\Model\FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: $leftAt,
            function: \App\Logic\Membership\Member\Model\MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: \App\Logic\Membership\Member\Model\PaymentMethod::BankTransfer,
            paymentInterval: \App\Logic\Membership\PaymentInterval::Yearly,
            paymentDay: \App\Logic\Membership\Member\Model\PaymentDay::First,
            payerType: $payerMemberId === null ? \App\Logic\Membership\Member\Model\PayerType::SelfPayer : \App\Logic\Membership\Member\Model\PayerType::OtherMember,
            payerMemberId: $payerMemberId,
            nextBookingMonth: 1,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }

    private function passwordGenerator(): AccessPasswordGeneratorInterface
    {
        $generator = $this->createStub(AccessPasswordGeneratorInterface::class);
        $generator->method('generate')->willReturn(self::PASSWORD);

        return $generator;
    }

    private function identifierGenerator(string $id): IdentifierGeneratorInterface
    {
        $generator = $this->createStub(IdentifierGeneratorInterface::class);
        $generator->method('generate')->willReturn($id);

        return $generator;
    }

    /**
     * Der eigentliche Mailversand ist für diese Tests irrelevant: `NotificationMailer::sendTo()`
     * wird in den No-op-Szenarien hier gar nicht erst aufgerufen.
     */
    private function noopMailer(): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));

        return new NotificationMailer(
            $this->createStub(MailerInterface::class),
            $emailSettingsManager,
            $this->realRenderer(),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
            'from@example.test',
            'Verein',
        );
    }

    /**
     * Ein `MailerInterface`, der die gebaute Mail abfängt statt sie zu verschicken —
     * `$capturedEmail` wird per Referenz befüllt.
     */
    private function configuredMailer(?Email &$capturedEmail): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $email) use (&$capturedEmail): void {
            $capturedEmail = $email;
        });

        return new NotificationMailer(
            $mailer,
            $emailSettingsManager,
            $this->realRenderer(),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
            'from@example.test',
            'Verein',
        );
    }

    private function realRenderer(): MailTemplateRenderer
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturnCallback(
            static fn (MailTemplateKey $key): MailTemplate => new MailTemplate($key, $key->defaultSubject(), $key->defaultBody()),
        );

        return new MailTemplateRenderer($manager, new MailContentRenderer(), $this->createStub(MailSignatureManagerInterface::class));
    }
}
