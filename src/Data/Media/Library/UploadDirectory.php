<?php

namespace App\Data\Media\Library;

/**
 * Bildet URLs unter `/uploads/` auf Dateien unterhalb des Upload-Verzeichnisses ab. Versteckte
 * Einträge (z. B. `.htaccess`) und Altdateien mit Bildquellen (`*.source.txt`) gehören nicht zur
 * Mediathek; Pfadsegmente mit `..` oder führendem Punkt werden nie aufgelöst.
 */
final readonly class UploadDirectory
{
    private const string URL_PREFIX = '/uploads/';

    public function __construct(private string $uploadsRootDirectory)
    {
    }

    public function folderPath(string $folder): ?string
    {
        if ($folder === '') {
            return $this->uploadsRootDirectory;
        }

        return $this->isSafeRelativePath($folder)
            ? $this->uploadsRootDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder)
            : null;
    }

    public function existingFolderPath(string $folder): ?string
    {
        $path = $this->folderPath($folder);

        return $path !== null && is_dir($path) ? $path : null;
    }

    public function filePathFromUrl(string $url): ?string
    {
        if (!str_starts_with($url, self::URL_PREFIX)) {
            return null;
        }
        $relative = substr($url, strlen(self::URL_PREFIX));
        if (!$this->isSafeRelativePath($relative) || str_ends_with($relative, '.source.txt')) {
            return null;
        }
        $path = $this->uploadsRootDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return is_file($path) ? $path : null;
    }

    public function urlFromPath(string $path): string
    {
        return self::URL_PREFIX.$this->relativePath($path);
    }

    public function folderOfPath(string $path): string
    {
        $folder = dirname($this->relativePath($path));

        return $folder === '.' ? '' : $folder;
    }

    /**
     * @return list<string> relative Ordnerpfade
     */
    public function folders(): array
    {
        if (!is_dir($this->uploadsRootDirectory)) {
            return [];
        }
        $folders = [];
        foreach ($this->entries($this->uploadsRootDirectory, true) as $path) {
            if (is_dir($path)) {
                $folders[] = $this->relativePath($path);
            }
        }

        return $folders;
    }

    /**
     * @return list<string> absolute Dateipfade
     */
    public function files(string $folder, bool $recursive): array
    {
        $directory = $this->existingFolderPath($folder);
        if ($directory === null) {
            return [];
        }
        $files = [];
        foreach ($this->entries($directory, $recursive) as $path) {
            if (is_file($path) && !str_ends_with($path, '.source.txt')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Erzeugt einen URL-tauglichen, im Ordner noch freien Dateinamen.
     */
    public function availableFilename(string $directory, string $originalName, string $extension): string
    {
        $basename = pathinfo(basename($originalName), PATHINFO_FILENAME);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename);
        if (is_string($transliterated)) {
            $basename = $transliterated;
        }
        $basename = mb_strtolower($basename);
        $basename = preg_replace('/[^a-z0-9]+/', '-', $basename);
        $basename = is_string($basename) ? trim($basename, '-') : '';
        $basename = mb_substr($basename === '' ? 'datei' : $basename, 0, 180);

        $filename = $basename.'.'.$extension;
        $suffix = 2;
        while (file_exists($directory.DIRECTORY_SEPARATOR.$filename)) {
            $filename = $basename.'-'.$suffix.'.'.$extension;
            ++$suffix;
        }

        return $filename;
    }

    /**
     * @return list<string>
     */
    private function entries(string $directory, bool $recursive): array
    {
        $names = scandir($directory);
        if ($names === false) {
            throw new \RuntimeException('Das Upload-Verzeichnis konnte nicht gelesen werden.');
        }
        $entries = [];
        foreach ($names as $name) {
            if (str_starts_with($name, '.')) {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            $entries[] = $path;
            if ($recursive && is_dir($path)) {
                array_push($entries, ...$this->entries($path, true));
            }
        }

        return $entries;
    }

    private function relativePath(string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($this->uploadsRootDirectory) + 1));
    }

    private function isSafeRelativePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return false;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return false;
            }
        }

        return true;
    }
}
