<?php

namespace App\Tests\Unit\Logic\Membership\MemberMessage\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\MemberMessage\Dto\ReplyToMemberMessageRequest;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use App\Logic\Membership\MemberMessage\UseCase\ReplyToMemberMessageUseCase;
use App\Logic\Settings\Email\Service\FreeTextMailer;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ReplyToMemberMessageUseCaseTest extends TestCase
{
    public function testSendsBrandedMailToTheGivenRecipientWithoutChangingTheStatus(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(
            static fn (Email $email): bool => $email->getTo()[0]->getAddress() === 'erika@example.test'
                && $email->getSubject() === 'Ihre Nachricht vom 01.06.2026'
                && $email->getTextBody() === "Hallo Erika,\n\nerledigt."
                && str_contains((string) $email->getHtmlBody(), '<!doctype html>')
                && $email->getFrom()[0]->getAddress() === 'verein@example.test',
        ));
        $manager = $this->createMock(MemberMessageManagerInterface::class);
        $manager->method('get')->willReturn($this->message());
        $manager->expects(self::never())->method('save');

        $this->useCase($manager, $mailer)->execute(new ReplyToMemberMessageRequest('id-1', ' erika@example.test ', 'Ihre Nachricht vom 01.06.2026', "Hallo Erika,\n\nerledigt."));
    }

    public function testRejectsInvalidRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $manager = $this->createStub(MemberMessageManagerInterface::class);
        $manager->method('get')->willReturn($this->message());

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($manager, $mailer)->execute(new ReplyToMemberMessageRequest('id-1', 'keine-adresse', 'Betreff', 'Text'));
    }

    private function useCase(MemberMessageManagerInterface $manager, MailerInterface $mailer): ReplyToMemberMessageUseCase
    {
        $logo = $this->createStub(EmailLogoProviderInterface::class);
        $logo->method('getLogoDataUri')->willReturn(null);

        return new ReplyToMemberMessageUseCase(
            $manager,
            new FreeTextMailer($mailer, new MailContentRenderer(), new BrandedEmailLayout(), $logo, 'verein@example.test', 'Naturbad Borkheide e.V.'),
        );
    }

    private function message(): MemberMessage
    {
        $submittedAt = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');

        return new MemberMessage('id-1', 'member-1', 'Bad-001', 'Erika Musterfrau', 'Meine Adresse hat sich geändert.', MemberMessageStatus::New, $submittedAt, $submittedAt);
    }
}
