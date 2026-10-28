<?php

namespace App\Logic\Media\Library\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class MediaFolder
{
    /** Ordner, in den der Seiteneditor hochlädt; er bleibt deshalb immer bestehen. */
    public const string DEFAULT_UPLOAD_FOLDER = 'media';

    public function __construct(
        public string $path,
        public int $fileCount,
    ) {
    }

    public function name(): string
    {
        $position = strrpos($this->path, '/');

        return $position === false ? $this->path : substr($this->path, $position + 1);
    }

    public function parentPath(): string
    {
        $position = strrpos($this->path, '/');

        return $position === false ? '' : substr($this->path, 0, $position);
    }

    public static function childPath(string $parentPath, string $name): string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1 || mb_strlen($name) > 60) {
            throw new BusinessRuleViolationException('Ordnernamen dürfen nur Kleinbuchstaben, Ziffern und Bindestriche enthalten (höchstens 60 Zeichen).');
        }
        $path = $parentPath === '' ? $name : $parentPath.'/'.$name;
        if (substr_count($path, '/') >= 5) {
            throw new BusinessRuleViolationException('Ordner können höchstens fünf Ebenen tief verschachtelt werden.');
        }

        return $path;
    }
}
