<?php

namespace App\Data\Content\SocialMedia\Mapper;

use App\Data\Content\SocialMedia\Entity\SocialMediaSettingsEntity;
use App\Logic\Content\SocialMedia\Model\SocialMediaLink;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\Model\SocialPlatform;

readonly class SocialMediaSettingsMapper
{
    public function toModel(SocialMediaSettingsEntity $entity): SocialMediaSettings
    {
        $links = [];
        foreach ($entity->getLinks() as $link) {
            // Eine aus `SocialPlatform` entfernte Plattform soll gespeicherte Einstellungen nicht unlesbar machen.
            $platform = SocialPlatform::tryFrom($link['platform']);
            if ($platform !== null) {
                $links[] = new SocialMediaLink($platform, $link['url']);
            }
        }

        return new SocialMediaSettings(visible: $entity->isVisible(), links: $links);
    }

    public function createEntity(SocialMediaSettings $settings, \DateTimeImmutable $updatedAt): SocialMediaSettingsEntity
    {
        return new SocialMediaSettingsEntity(
            id: SocialMediaSettingsEntity::ID,
            visible: $settings->visible,
            links: $this->links($settings),
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(SocialMediaSettings $settings, SocialMediaSettingsEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($settings->visible, $this->links($settings), $updatedAt);
    }

    /**
     * @return list<array{platform: string, url: string}>
     */
    private function links(SocialMediaSettings $settings): array
    {
        return array_map(
            static fn (SocialMediaLink $link): array => ['platform' => $link->platform->value, 'url' => $link->url],
            $settings->links,
        );
    }
}
