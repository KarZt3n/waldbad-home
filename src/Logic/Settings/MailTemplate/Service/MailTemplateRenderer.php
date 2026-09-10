<?php

namespace App\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Ersetzt in einer Vorlage (siehe `MailTemplateKey`) die Platzhalter `{{name}}` durch die beim
 * Versand übergebenen Werte. Unbekannte Platzhalter im Text (Tippfehler, veraltete Vorlage nach
 * einer Änderung von `MailTemplateKey::placeholders()`) bleiben absichtlich unverändert im Text
 * stehen, statt eine Fehlermeldung auszulösen — eine E-Mail mit sichtbarem `{{tippfehler}}` ist
 * besser als ein fehlgeschlagener Versand.
 *
 * Ist einer Vorlage eine Signatur zugeordnet (`MailTemplate::$signatureId`), wird deren — ebenfalls
 * platzhalterersetzter — Text an Betreff-losem Text/HTML angehängt, statt seinen Inhalt in jede
 * Vorlage einzeln zu kopieren: eine spätere Änderung der Signatur wirkt sich so automatisch auf
 * jede Vorlage aus, die sie referenziert.
 */
readonly class MailTemplateRenderer
{
    public function __construct(
        private MailTemplateManagerInterface $manager,
        private MailContentRenderer $contentRenderer,
        private MailSignatureManagerInterface $signatures,
    ) {
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks    siehe `renderText()`
     *
     * @return array{subject: string, body: string, html: string} siehe `renderText()`
     */
    public function render(MailTemplateKey $key, array $placeholders, array $htmlBlocks = []): array
    {
        $template = $this->manager->resolve($key);

        return $this->renderText($template->subject, $template->body, $placeholders, $htmlBlocks, $template->signatureId);
    }

    /**
     * Wie `render()`, aber auf beliebig übergebenem Betreff/Text statt einer gespeicherten Vorlage
     * — für die „Vorschau“ im Mailvorlagen-Editor (siehe `PreviewMailTemplateUseCase`), die den
     * gerade in der Oberfläche bearbeiteten (ggf. noch ungespeicherten) Text zeigen soll.
     *
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
     *                                              gleichnamigen Wert aus `$placeholders`.
     * @param string|null           $signatureId   Id einer über `showMailSignatures` gepflegten
     *                                              Signatur (siehe `MailSignature`), deren Text
     *                                              (mit denselben `$placeholders`/`$htmlBlocks`
     *                                              ersetzt) an Text/HTML angehängt wird. Existiert
     *                                              sie nicht (mehr), wird einfach nichts angehängt
     *                                              — best effort, kein Fehler.
     *
     * @return array{subject: string, body: string, html: string} `html` ist nur das reine
     *                                                              Inhaltsfragment (Absätze/Zeilen-
     *                                                              umbrüche) — den gestalteten Rahmen
     *                                                              (Logo, Farben, Fußzeile) legt erst
     *                                                              `BrandedEmailLayout` beim Versand
     *                                                              darum, siehe `NotificationMailer`.
     */
    public function renderText(string $subject, string $body, array $placeholders, array $htmlBlocks = [], ?string $signatureId = null): array
    {
        $renderedSubject = $this->substitute($subject, $placeholders);
        $renderedBody = $this->substitute($body, $placeholders);
        $html = $this->buildHtmlFragment($body, $placeholders, $htmlBlocks);

        $signature = $signatureId !== null ? $this->signatures->find($signatureId) : null;
        if ($signature !== null) {
            $renderedBody = rtrim($renderedBody)."\n\n".$this->substitute($signature->body, $placeholders);
            $html .= $this->buildHtmlFragment($signature->body, $placeholders, $htmlBlocks);
        }

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'html' => $html,
        ];
    }

    /**
     * @param array<string, string> $placeholders
     */
    private function substitute(string $text, array $placeholders): string
    {
        $search = array_map(static fn (string $name): string => '{{'.$name.'}}', array_keys($placeholders));

        return str_replace($search, array_values($placeholders), $text);
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks
     */
    private function buildHtmlFragment(string $text, array $placeholders, array $htmlBlocks): string
    {
        $textOnlyPlaceholders = array_diff_key($placeholders, $htmlBlocks);
        $htmlSource = $this->substitute($text, $textOnlyPlaceholders);
        $rawBlocks = [];
        foreach ($htmlBlocks as $name => $html) {
            $rawBlocks['{{'.$name.'}}'] = $html;
        }

        return $this->contentRenderer->toHtmlFragment($htmlSource, $rawBlocks);
    }
}
