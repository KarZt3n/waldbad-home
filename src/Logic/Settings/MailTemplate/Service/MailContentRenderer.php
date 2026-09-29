<?php

namespace App\Logic\Settings\MailTemplate\Service;

/**
 * Wandelt den einfachen, admin-editierbaren Klartext einer Mailvorlage (siehe `MailTemplateKey`,
 * bereits mit ersetzten Platzhaltern) in ein sicheres HTML-Fragment um: Absätze durch Leerzeilen
 * getrennt, einzelne Zeilenumbrüche als `<br>`. Admins bearbeiten weiterhin nur Klartext (kein
 * Risiko durch kaputtes/böswilliges HTML in einer Vorlage) — den optischen Rahmen (Kopfzeile mit
 * Logo, Farben, Fußzeile) liefert `BrandedEmailLayout` außenherum.
 */
readonly class MailContentRenderer
{
    public function toHtmlFragment(string $text): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $trimmed = trim($paragraph);
            if ($trimmed === '') {
                continue;
            }

            $html .= sprintf('<p style="margin:0 0 16px;">%s</p>', nl2br(htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'), false));
        }

        return $html;
    }
}
