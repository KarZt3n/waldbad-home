<?php

namespace App\UI\Media\Library\Http;

use App\Logic\Media\Library\Model\MediaUpload;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MediaUploadRequestMapper
{
    public function upload(Request $request): MediaUpload
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $contentLength = $request->headers->get('Content-Length');
            if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > MediaUpload::MAX_SIZE) {
                throw new BadRequestHttpException('Die Datei darf maximal 10 MB groß sein.');
            }
            throw new BadRequestHttpException('Bitte wählen Sie eine Datei aus.');
        }
        if (!$file->isValid()) {
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new BadRequestHttpException('Die Datei darf maximal 10 MB groß sein.');
            }
            throw new BadRequestHttpException('Die Datei konnte nicht vollständig hochgeladen werden.');
        }
        $size = $file->getSize();
        if ($size === false) {
            throw new BadRequestHttpException('Die Dateigröße konnte nicht ermittelt werden.');
        }
        $source = $request->request->get('source');
        if ($source !== null && !is_string($source)) {
            throw new BadRequestHttpException('Die Quelle muss eine Zeichenkette sein.');
        }
        $source = $source === null ? '' : trim(strip_tags($source));
        if (mb_strlen($source) > 300) {
            throw new BadRequestHttpException('Die Quelle darf höchstens 300 Zeichen lang sein.');
        }

        return new MediaUpload(
            temporaryPath: $file->getPathname(),
            originalName: $file->getClientOriginalName(),
            size: $size,
            source: $source === '' ? null : $source,
        );
    }

    public function url(mixed $url): string
    {
        if (!is_string($url) || trim($url) === '') {
            throw new BadRequestHttpException('Die Datei-URL ist erforderlich.');
        }
        $url = trim($url);
        if (!str_starts_with($url, '/uploads/') || mb_strlen($url) > 255) {
            throw new BadRequestHttpException('Die Datei-URL ist ungültig.');
        }

        return $url;
    }

    public function folderPath(mixed $path): string
    {
        if ($path === null) {
            return '';
        }
        if (!is_string($path) || mb_strlen($path) > 255) {
            throw new BadRequestHttpException('Der Ordnerpfad ist ungültig.');
        }

        return trim($path, " /");
    }
}
