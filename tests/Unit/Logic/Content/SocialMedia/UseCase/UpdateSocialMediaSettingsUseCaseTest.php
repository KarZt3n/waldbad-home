<?php

namespace App\Tests\Unit\Logic\Content\SocialMedia\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkRequest;
use App\Logic\Content\SocialMedia\Dto\UpdateSocialMediaSettingsRequest;
use App\Logic\Content\SocialMedia\Manager\SocialMediaSettingsManagerInterface;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\Model\SocialPlatform;
use App\Logic\Content\SocialMedia\UseCase\UpdateSocialMediaSettingsUseCase;
use PHPUnit\Framework\TestCase;

final class UpdateSocialMediaSettingsUseCaseTest extends TestCase
{
    public function testSavesVisibilityAndLinksInGivenOrder(): void
    {
        $manager = $this->createMock(SocialMediaSettingsManagerInterface::class);
        $manager->expects(self::once())->method('save')->with(self::callback(static function (SocialMediaSettings $settings): bool {
            return $settings->visible
                && array_map(static fn ($link): string => $link->platform->value, $settings->links) === ['facebook', 'instagram']
                && $settings->links[1]->url === 'https://www.instagram.com/waldbad';
        }))->willReturnArgument(0);

        (new UpdateSocialMediaSettingsUseCase($manager))->execute(new UpdateSocialMediaSettingsRequest(true, [
            new SocialMediaLinkRequest(SocialPlatform::Facebook, 'https://www.facebook.com/waldbad'),
            new SocialMediaLinkRequest(SocialPlatform::Instagram, ' https://www.instagram.com/waldbad '),
        ]));
    }

    public function testDoesNotSaveInvalidLinks(): void
    {
        $manager = $this->createMock(SocialMediaSettingsManagerInterface::class);
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        (new UpdateSocialMediaSettingsUseCase($manager))->execute(new UpdateSocialMediaSettingsRequest(true, [
            new SocialMediaLinkRequest(SocialPlatform::Instagram, 'instagram.com/waldbad'),
        ]));
    }
}
