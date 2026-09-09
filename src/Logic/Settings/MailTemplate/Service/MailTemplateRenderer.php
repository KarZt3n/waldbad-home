<?php

namespace App\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Ersetzt in einer Vorlage (siehe `MailTemplateKey`) die Platzhalter `{{name}}` durch die beim
 * Versand übergebenen Werte. Unbekannte Platzhalter im Text (Tippfehler, veraltete Vorlage nach
 * einer Änderung von `MailTemplateKey::placeholders()`) bleiben absichtlich unverändert im Text
 * stehen, statt eine Fehlermeldung auszulösen — eine E-Mail mit sichtbarem `{{tippfehler}}` ist
 * besser als ein fehlgeschlagener Versand.
 */
readonly class MailTemplateRenderer
{
    public function __construct(
        private MailTemplateManagerInterface $manager,
        private MailContentRenderer $contentRenderer,
    ) {
    }

    /**
     * @param array<string, string> $placeholders Klartext-Werte, wie gehabt für Betreff und den
     *                                             Text-Fallback verwendet; in der HTML-Ansicht wird
     *                                             jeder Wert escaped in den gestalteten Text
     *                                             eingesetzt (siehe `MailContentRenderer`).
     * @param array<string, string> $htmlBlocks    Für ausgewählte Platzhalter (z. B. `beitraege`,
     *                                              siehe `ReleaseMembershipApplicationUseCase`) eine
     *                                              eigene, bereits fertige HTML-Darstellung (z. B.
     *                                              eine `<ul>`-Liste statt einer Aufzählung mit
     *                                              Gedankenstrichen) — nur für die HTML-Ansicht;
     *                                              Betreff und Text-Fallback verwenden weiterhin den
     *                                              gleichnamigen Wert aus `$placeholders`. Ein hier
     *                                              genannter Platzhalter muss im Vorlagentext auf
     *                                              einer eigenen, durch Leerzeilen abgetrennten Zeile
     *                                              stehen (sonst bleibt er unersetzt).
     *
     * @return array{subject: string, body: string, html: string} `html` ist nur das reine
     *                                                              Inhaltsfragment (Absätze/Zeilen-
     *                                                              umbrüche) — den gestalteten Rahmen
     *                                                              (Logo, Farben, Fußzeile) legt erst
     *                                                              `BrandedEmailLayout` beim Versand
     *                                                              darum, siehe `NotificationMailer`.
     */
    public function render(MailTemplateKey $key, array $placeholders, array $htmlBlocks = []): array
    {
        $template = $this->manager->resolve($key);
        $search = array_map(static fn (string $name): string => '{{'.$name.'}}', array_keys($placeholders));

        $subject = str_replace($search, array_values($placeholders), $template->subject);
        $body = str_replace($search, array_values($placeholders), $template->body);

        $textOnlyPlaceholders = array_diff_key($placeholders, $htmlBlocks);
        $htmlSearch = array_map(static fn (string $name): string => '{{'.$name.'}}', array_keys($textOnlyPlaceholders));
        $htmlSourceBody = str_replace($htmlSearch, array_values($textOnlyPlaceholders), $template->body);
        $rawBlocks = [];
        foreach ($htmlBlocks as $name => $html) {
            $rawBlocks['{{'.$name.'}}'] = $html;
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'html' => $this->contentRenderer->toHtmlFragment($htmlSourceBody, $rawBlocks),
        ];
    }
}
