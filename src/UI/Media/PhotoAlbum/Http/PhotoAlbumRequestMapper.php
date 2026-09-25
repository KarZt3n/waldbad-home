<?php

namespace App\UI\Media\PhotoAlbum\Http;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumActionInput;
use App\Logic\Media\PhotoAlbum\Dto\SavePhotoAlbumRequest;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Syntaktische Prüfung und Abbildung eines Foto-Eintrags — gemeinsam genutzt von der Admin-API
 * (JSON-Payload) und dem Import-Befehl (JSON-Datei), damit beide dieselben Regeln anwenden.
 */
readonly class PhotoAlbumRequestMapper
{
    /**
     * @param array<mixed> $data
     */
    public function fromArray(?string $id, array $data): SavePhotoAlbumRequest
    {
        $title = $data['title'] ?? null;
        $rawDate = $data['date'] ?? null;
        $visible = $data['visible'] ?? true;
        $actions = $data['actions'] ?? [];
        if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 250) {
            throw new BadRequestHttpException('Ein Foto-Eintrag benötigt einen Titel mit höchstens 250 Zeichen.');
        }
        if (!is_string($rawDate)) {
            throw new BadRequestHttpException('Das Datum eines Foto-Eintrags ist erforderlich.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
        if ($date === false || $date->format('Y-m-d') !== $rawDate) {
            throw new BadRequestHttpException('Das Datum muss ein gültiges Datum im Format JJJJ-MM-TT sein.');
        }
        if (!is_bool($visible)) {
            throw new BadRequestHttpException('Die Sichtbarkeit muss als Wahrheitswert angegeben werden.');
        }
        if (!is_array($actions) || !array_is_list($actions)) {
            throw new BadRequestHttpException('Die Aktionsbuttons müssen als Liste angegeben werden.');
        }

        return new SavePhotoAlbumRequest(
            id: $id,
            title: trim($title),
            date: $date,
            visible: $visible,
            actions: array_map($this->action(...), $actions),
        );
    }

    private function action(mixed $action): PhotoAlbumActionInput
    {
        $label = is_array($action) ? ($action['label'] ?? null) : null;
        $url = is_array($action) ? ($action['url'] ?? null) : null;
        $pageId = is_array($action) ? ($action['pageId'] ?? null) : null;
        if (!is_string($label) || ($url !== null && !is_string($url)) || ($pageId !== null && !is_string($pageId))) {
            throw new BadRequestHttpException('Ein Aktionsbutton benötigt Beschriftung und ein gültiges Ziel.');
        }

        return new PhotoAlbumActionInput(trim(strip_tags($label)), $url, $pageId);
    }
}
