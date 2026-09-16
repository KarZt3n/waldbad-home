<?php

namespace App\UI\IdentityAccess\LoginToken\Http;

use App\Logic\IdentityAccess\LoginToken\LoginLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class LoginLinkBuilder implements LoginLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(string $token): string
    {
        return $this->urlGenerator->generate(
            'admin_app',
            ['login_token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
