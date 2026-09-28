<?php

namespace App\UI\Membership\Member\Http;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Verpackt/entpackt den formatierten Mitglieder-Export (siehe `MemberExportFormatter`) als ZIP —
 * optional AES-256-verschlüsselt, wenn `ProtectedAction::MembersExport` per PIN geschützt ist
 * (siehe `AdminMemberController::export()`/`import()`). Das Dateiformat wird beim Import aus dem
 * Namen des einzigen ZIP-Eintrags ermittelt statt separat abgefragt.
 */
readonly class MemberExportZipArchive
{
    public function build(string $content, string $format, ?string $password): string
    {
        $path = tempnam(sys_get_temp_dir(), 'member-export-');
        if ($path === false) {
            throw new \RuntimeException('Es konnte keine temporäre Datei für den Export angelegt werden.');
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Das ZIP-Archiv konnte nicht erstellt werden.');
            }
            $entryName = "mitglieder.{$format}";
            $zip->addFromString($entryName, $content);
            if ($password !== null) {
                $zip->setEncryptionName($entryName, \ZipArchive::EM_AES_256, $password);
            }
            $zip->close();

            $zipContent = file_get_contents($path);
            if ($zipContent === false) {
                throw new \RuntimeException('Das ZIP-Archiv konnte nicht gelesen werden.');
            }

            return $zipContent;
        } finally {
            @unlink($path);
        }
    }

    /**
     * Ob der Archiv-Eintrag verschlüsselt ist — nur dann braucht der Import ein Passwort.
     */
    public function isEncrypted(string $zipContent): bool
    {
        return $this->withArchive($zipContent, static function (\ZipArchive $zip): bool {
            $stat = $zip->statIndex(0);

            return $stat !== false && $stat['encryption_method'] !== \ZipArchive::EM_NONE;
        });
    }

    /**
     * @param list<string> $allowedFormats
     * @return array{content: string, format: string}
     */
    public function extract(string $zipContent, ?string $password, array $allowedFormats): array
    {
        return $this->withArchive($zipContent, static function (\ZipArchive $zip) use ($password, $allowedFormats): array {
            $format = strtolower(pathinfo($zip->getNameIndex(0) ?: '', PATHINFO_EXTENSION));
            if (!in_array($format, $allowedFormats, true)) {
                throw new BadRequestHttpException('Das Archiv enthält kein unterstütztes Dateiformat.');
            }
            if ($password !== null) {
                $zip->setPassword($password);
            }
            $content = $zip->getFromIndex(0);
            if ($content === false) {
                throw new BadRequestHttpException('Das Passwort ist falsch oder die Datei ist beschädigt.');
            }

            return ['content' => $content, 'format' => $format];
        });
    }

    /**
     * @template T
     * @param callable(\ZipArchive): T $callback
     * @return T
     */
    private function withArchive(string $zipContent, callable $callback): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'member-import-');
        if ($path === false) {
            throw new \RuntimeException('Es konnte keine temporäre Datei für den Import angelegt werden.');
        }
        try {
            if (file_put_contents($path, $zipContent) === false) {
                throw new \RuntimeException('Die hochgeladene Datei konnte nicht gelesen werden.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true || $zip->numFiles !== 1) {
                throw new BadRequestHttpException('Die Datei ist kein gültiges Mitglieder-Export-Archiv.');
            }
            try {
                return $callback($zip);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($path);
        }
    }
}
