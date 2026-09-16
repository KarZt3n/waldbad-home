<?php

namespace App\UI\IdentityAccess\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

/**
 * Liest den Access-Token nicht — wie Symfonys Standard-Extraktoren — aus dem `Authorization`-Header
 * oder der Query, sondern aus dem httpOnly-Cookie, das `AuthenticationController::login()`/
 * `refresh()` setzt (siehe `AccessTokenHandler`).
 */
final readonly class CookieAccessTokenExtractor implements AccessTokenExtractorInterface
{
    public const string COOKIE_NAME = 'cms_access_token';

    public function extractAccessToken(Request $request): ?string
    {
        $value = $request->cookies->get(self::COOKIE_NAME);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
