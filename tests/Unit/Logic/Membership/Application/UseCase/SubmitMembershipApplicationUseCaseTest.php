<?php

namespace App\Tests\Unit\Logic\Membership\Application\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\EmailDeliverabilityCheckerInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Application\Dto\ApplicantInput;
use App\Logic\Membership\Application\Dto\SubmitMembershipApplicationRequest;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Service\ConfiguredMailTransportFactory;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use App\Logic\Membership\Application\UseCase\SubmitMembershipApplicationUseCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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

    /**
     * @param list<ApplicantInput> $applicants
     */
    private function request(array $applicants): SubmitMembershipApplicationRequest
    {
        return new SubmitMembershipApplicationRequest(
            membershipType: count($applicants) > 1 ? MembershipType::Family : MembershipType::Individual,
            applicants: $applicants,
            accountHolder: 'Erika Musterfrau',
            iban: 'DE89370400440532013000',
            bankName: null,
            signerName: 'Erika Musterfrau',
            emailConsent: true,
            declarationVersion: '2026-01-01',
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
        // Kein Mailserver konfiguriert: NotificationMailer::notify() wird dadurch zum No-op, ohne
        // dass hier ein echter Transport aufgebaut werden müsste.
        $emailSettingsManager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));
        $transportFactory = $this->createStub(ConfiguredMailTransportFactory::class);
        // Wird durch das nicht konfigurierte $emailSettingsManager oben nie tatsächlich aufgerufen.
        $templateRenderer = $this->createStub(MailTemplateRenderer::class);
        $logoProvider = $this->createStub(EmailLogoProviderInterface::class);
        $notificationMailer = new NotificationMailer($emailSettingsManager, $transportFactory, $templateRenderer, new BrandedEmailLayout(), $logoProvider, new NullLogger());

        (new SubmitMembershipApplicationUseCase($manager, $identifierGenerator, $clock, $notificationMailer, $checker))
            ->execute($request);
    }
}
