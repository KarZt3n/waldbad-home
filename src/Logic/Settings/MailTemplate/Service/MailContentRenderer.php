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
    /**
     * @param array<string, string> $rawBlocks Bildet einen Platzhalter-Token (z. B. `{{beitraege}}`)
     *                                          auf bereits fertiges, selbst erzeugtes HTML ab (z. B.
     *                                          eine `<ul>`-Liste für die Beitragsübersicht, siehe
     *                                          `ReleaseMembershipApplicationUseCase`) — dort wird es
     *                                          unverändert (nicht escaped) eingesetzt, statt wie der
     *                                          übrige, von Admins gepflegte Text escaped zu werden.
     *                                          Steht der Token allein in seinem Absatz (durch
     *                                          Leerzeilen abgetrennt), entfällt zusätzlich der
     *                                          `<p>`-Wrapper — passend für block-artiges HTML wie
     *                                          eine Liste. Steht er (z. B. bei einer älteren,
     *                                          bereits gespeicherten Vorlage ohne diese Abtrennung)
     *                                          mitten in einem Absatz, wird trotzdem korrekt
     *                                          ersetzt, nur eben innerhalb des umgebenden `<p>`.
     */
    public function toHtmlFragment(string $text, array $rawBlocks = []): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $trimmed = trim($paragraph);
            if ($trimmed === '') {
                continue;
            }

            if (array_key_exists($trimmed, $rawBlocks)) {
                $html .= $rawBlocks[$trimmed];
                continue;
            }

            // Escaping zuerst: die Platzhalter-Token selbst (z. B. „{{beitraege}}“) enthalten keine
            // HTML-Sonderzeichen und überstehen es unverändert — das nachträgliche Einsetzen des
            // rohen HTML ist deshalb sicher, auch wenn der Token nicht allein in seinem Absatz steht.
            $escaped = nl2br(htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'), false);
            $escaped = str_replace(array_keys($rawBlocks), array_values($rawBlocks), $escaped);
            $html .= sprintf('<p style="margin:0 0 16px;">%s</p>', $escaped);
        }

        return $html;
    }
}
