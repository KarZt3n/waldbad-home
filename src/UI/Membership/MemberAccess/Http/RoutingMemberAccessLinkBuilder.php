<?php

namespace App\UI\Membership\MemberAccess\Http;

use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class RoutingMemberAccessLinkBuilder implements MemberAccessLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(string $token): string
    {
        return $this->urlGenerator->generate(
            'public_member_access',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
