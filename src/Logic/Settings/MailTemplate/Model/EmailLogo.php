<?php

namespace App\Logic\Settings\MailTemplate\Model;

/**
 * Vereinslogo für den Kopfbereich einer gestalteten HTML-Mail (siehe `BrandedEmailLayout`). Beim
 * Versand hängt es als eingebettetes Bild (CID) an der Mail: `data:`-URIs in `<img>` entfernen Gmail
 * und andere Webmailer, externe URLs blenden viele Programme erst nach Klick ein. Nur die Vorschau im
 * Browser nutzt die `data:`-URI.
 */
readonly class EmailLogo
{
    public const string CONTENT_ID = 'logo';

    public function __construct(
        public string $contents,
        public string $mimeType,
    ) {
    }

    public function dataUri(): string
    {
        return sprintf('data:%s;base64,%s', $this->mimeType, base64_encode($this->contents));
    }

    /** Bildquelle für das HTML einer versandten Mail; Symfony ersetzt sie durch die Content-ID des eingebetteten Bildes. */
    public function embeddedSource(): string
    {
        return 'cid:'.self::CONTENT_ID;
    }
}
