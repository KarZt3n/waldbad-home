<?php

namespace App\UI\Membership\Member\EmailConsent\Http;

use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class MemberEmailConsentLinkBuilder implements MemberEmailConsentLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(string $token): string
    {
        return $this->urlGenerator->generate(
            'public_email_consent',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
