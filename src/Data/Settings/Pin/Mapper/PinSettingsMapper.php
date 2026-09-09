<?php

namespace App\Data\Settings\Pin\Mapper;

use App\Data\Settings\Pin\Entity\PinSettingsEntity;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Model\ProtectedAction;

readonly class PinSettingsMapper
{
    public function toModel(PinSettingsEntity $entity): PinSettings
    {
        return new PinSettings(
            globalPinHash: $entity->getGlobalPinHash(),
            protectedActions: array_map(
                static fn (string $value): ProtectedAction => ProtectedAction::from($value),
                $entity->getProtectedActions(),
            ),
            actionPinHashes: $entity->getActionPinHashes(),
        );
    }

    public function createEntity(PinSettings $settings, \DateTimeImmutable $updatedAt): PinSettingsEntity
    {
        return new PinSettingsEntity(
            id: PinSettingsEntity::ID,
            globalPinHash: $settings->globalPinHash,
            protectedActions: $this->actionValues($settings),
            actionPinHashes: $settings->actionPinHashes,
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(PinSettings $settings, PinSettingsEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($settings->globalPinHash, $this->actionValues($settings), $settings->actionPinHashes, $updatedAt);
    }

    /**
     * @return list<string>
     */
    private function actionValues(PinSettings $settings): array
    {
        return array_map(static fn (ProtectedAction $action): string => $action->value, $settings->protectedActions);
    }
}
