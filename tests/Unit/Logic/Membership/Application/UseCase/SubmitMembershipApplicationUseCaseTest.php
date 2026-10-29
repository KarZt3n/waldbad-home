<?php

namespace App\Tests\Unit\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\EmailDeliverabilityCheckerInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Application\Dto\ApplicantInput;
use App\Logic\Membership\Application\Dto\SubmitMembershipApplicationRequest;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\MembershipDeclarationProviderInterface;
use App\Logic\Membership\Application\Model\MembershipDeclaration;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use App\Logic\Membership\Application\UseCase\SubmitMembershipApplicationUseCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;

final class SubmitMembershipApplicationUseCaseTest extends TestCase
{
    public function testChecksEveryDistinctEmailExactlyOnce(): void
    {
        // Zwei Personen teilen sich dieselbe (von Person 1 übernommene) Adresse, eine dritte hat
        // eine eigene — macht insgesamt nur zwei tatsächlich zu prüfende Adressen.
        $request = $this->request([
            $this->applicant(email: 'shared@example.test'),
            $this->applicant(email: 'shared@example.test'),
            $this->applicant(email: 'own@example.test'),
        ]);

        $checker = $this->createMock(EmailDeliverabilityCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('isDeliverable')->willReturn(true);

        $this->execute($request, $checker);
    }

    public function testSkipsApplicantsWithoutAnEmail(): void
    {
        $request = $this->request([
            $this->applicant(email: 'shared@example.test'),
            $this->applicant(email: null),
        ]);

        $checker = $this->createMock(EmailDeliverabilityCheckerInterface::class);
        $checker->expects(self::once())->method('isDeliverable')->with('shared@example.test')->willReturn(true);

        $this->execute($request, $checker);
    }

    public function testRejectsSubmissionWithAnUndeliverableEmail(): void
    {
        $request = $this->request([$this->applicant(email: 'broken@nonexistent.invalidtld')]);

        $checker = $this->createStub(EmailDeliverabilityCheckerInterface::class);
        $checker->method('isDeliverable')->willReturn(false);

        $manager = $this->createMock(MembershipApplicationManagerInterface::class);
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessageMatches('/broken@nonexistent\.invalidtld/');

        $this->execute($request, $checker, $manager);
    }

    /** Der gespeicherte Antrag hält fest, welchem Wortlaut zugestimmt wurde — die freiwillige E-Mail-Einwilligung nur, wenn erteilt. */
    public function testStoresTheVersionAndWordingOfTheAcceptedDeclarations(): void
    {
        $checker = $this->createStub(EmailDeliverabilityCheckerInterface::class);
        $checker->method('isDeliverable')->willReturn(true);
        $manager = $this->createMock(MembershipApplicationManagerInterface::class);
        $manager->expects(self::once())->method('save')->with(self::callback(
            static fn (MembershipApplication $application): bool => $application->declarationVersion === 'v-current'
                && $application->acceptedDeclarations === ['Satzung', 'Datenschutz', 'SEPA'],
        ))->willReturnArgument(0);

        $this->execute($this->request([$this->applicant(email: 'erika@example.test')], emailConsent: false), $checker, $manager);
    }

    public function testRefusesWhenTheDeclarationsChangedWhileTheFormWasOpen(): void
    {
        $manager = $this->createMock(MembershipApplicationManagerInterface::class);
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('zwischenzeitlich geändert');

        $this->execute(
            $this->request([$this->applicant(email: 'erika@example.test')], declarationVersion: 'v-old'),
            $this->createStub(EmailDeliverabilityCheckerInterface::class),
            $manager,
        );
    }

    /**
     * @param list<ApplicantInput> $applicants
     */
    private function request(array $applicants, bool $emailConsent = true, string $declarationVersion = 'v-current'): SubmitMembershipApplicationRequest
    {
        return new SubmitMembershipApplicationRequest(
            membershipType: count($applicants) > 1 ? MembershipType::Family : MembershipType::Individual,
            applicants: $applicants,
            accountHolder: 'Erika Musterfrau',
            iban: 'DE89370400440532013000',
            bankName: null,
            signerName: 'Erika Musterfrau',
            emailConsent: $emailConsent,
            declarationVersion: $declarationVersion,
        );
    }

    private function applicant(?string $email): ApplicantInput
    {
        return new ApplicantInput(
            salutation: Salutation::Diverse,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger',
            houseNumber: '14',
            postalCode: '14822',
            city: 'Borkheide',
            phone: null,
            email: $email,
        );
    }

    private function execute(
        SubmitMembershipApplicationRequest $request,
        EmailDeliverabilityCheckerInterface $checker,
        ?MembershipApplicationManagerInterface $manager = null,
    ): void {
        if ($manager === null) {
            $manager = $this->createStub(MembershipApplicationManagerInterface::class);
            $manager->method('save')->willReturnCallback(static fn (MembershipApplication $application): MembershipApplication => $application);
        }

        $identifierGenerator = $this->createStub(IdentifierGeneratorInterface::class);
        $identifierGenerator->method('generate')->willReturnOnConsecutiveCalls(...array_map(
            static fn (int $i): string => 'id-'.$i,
            range(1, 20),
        ));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        // Kein Empfänger für das Ereignis hinterlegt: NotificationMailer::notify() wird dadurch zum
        // No-op, ohne dass hier ein echter Mailer aufgebaut werden müsste.
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));
        $templateRenderer = $this->createStub(MailTemplateRenderer::class);
        $logoProvider = $this->createStub(EmailLogoProviderInterface::class);
        $notificationMailer = new NotificationMailer(
            $this->createStub(MailerInterface::class),
            $emailSettingsManager,
            $templateRenderer,
            new BrandedEmailLayout(),
            $logoProvider,
            new NullLogger(),
            'from@example.test',
            'Verein',
        );

        $declarations = $this->createStub(MembershipDeclarationProviderInterface::class);
        $declarations->method('current')->willReturn(new MembershipDeclaration('v-current', 'Satzung', 'Datenschutz', 'SEPA', 'E-Mail'));

        (new SubmitMembershipApplicationUseCase($manager, $identifierGenerator, $clock, $notificationMailer, $checker, $declarations))
            ->execute($request);
    }
}
