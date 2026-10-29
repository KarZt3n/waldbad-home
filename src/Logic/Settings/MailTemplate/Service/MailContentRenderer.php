<?php

namespace App\Logic\Settings\MailTemplate\Service;

/**
 * Wandelt den einfachen, admin-editierbaren Klartext einer Mailvorlage (siehe `MailTemplateKey`,
 * bereits mit ersetzten Platzhaltern) in ein sicheres HTML-Fragment um: Absätze durch Leerzeilen
 * getrennt, einzelne Zeilenumbrüche als `<br>`. Admins bearbeiten weiterhin nur Klartext (kein
 * Risiko durch kaputtes/böswilliges HTML in einer Vorlage) — den optischen Rahmen (Kopfzeile mit
 * Logo, Farben, Fußzeile) liefert `BrandedEmailLayout` außenherum.
 *
 * Eine Zeile der Form `[[Beschriftung|{{link}}]]` wird zum Button (im Text-Teil „Beschriftung: URL“),
 * `**Text**` wird fett (im Text-Teil ohne Sternchen). Beides entsteht nur aus dem Vorlagentext selbst:
 * `prepareTemplate()` ersetzt es vor dem Einsetzen der Platzhalter durch Marken, und `neutralize()`
 * entfernt diese Marken aus den eingesetzten Werten — so kann z. B. die Nachricht eines Gastes keinen
 * Button mit fremdem Link in eine Vereinsmail einbauen.
 */
readonly class MailContentRenderer
{
    private const string BUTTON_LINE = '/^[ \t]*\[\[([^|\]\n]+)\|([^\]\n]+)\]\][ \t]*$/m';
    private const string BOLD = '/\*\*([^*\n]+)\*\*/';
    private const string MARKER = "\u{E000}";
    private const string BOLD_MARKER = "\u{E001}";
    // Farben aus `BrandedEmailLayout` (FOREST_900, WHITE).
    private const string PRIMARY = '#123f2c';
    private const string WHITE = '#ffffff';

    /**
     * @return array{0: string, 1: list<array{label: string, url: string}>} Vorlagentext mit Marken statt
     *                                                                    Button-Zeilen und Fettdruck sowie
     *                                                                    die Buttons (noch mit Platzhaltern)
     */
    public function prepareTemplate(string $template): array
    {
        $template = preg_replace(self::BOLD, self::BOLD_MARKER.'$1'.self::BOLD_MARKER, $template) ?? $template;
        $buttons = [];
        $text = preg_replace_callback(self::BUTTON_LINE, static function (array $match) use (&$buttons): string {
            $buttons[] = ['label' => trim($match[1]), 'url' => trim($match[2])];

            return self::MARKER.(count($buttons) - 1).self::MARKER;
        }, $template);

        return [$text ?? $template, $buttons];
    }

    /** Vorlagentext für den Klartext-Teil der Mail: Buttons als „Beschriftung: URL“, Fettdruck ohne Sternchen. */
    public function toPlainText(string $template): string
    {
        $text = preg_replace(self::BUTTON_LINE, '$1: $2', $template) ?? $template;

        return preg_replace(self::BOLD, '$1', $text) ?? $text;
    }

    /** Entfernt Button- und Fettdruck-Marken aus einem einzusetzenden Wert. */
    public function neutralize(string $value): string
    {
        return str_replace([self::MARKER, self::BOLD_MARKER], '', $value);
    }

    /**
     * @param list<array{label: string, url: string}> $buttons Buttons zu den Marken aus `prepareTemplate()`,
     *                                                        mit bereits ersetzten Platzhaltern
     */
    public function toHtmlFragment(string $text, array $buttons = []): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $lines = [];
            $buttonRow = [];
            foreach (explode("\n", trim($paragraph)) as $line) {
                $button = preg_match('/^'.self::MARKER.'(\d+)'.self::MARKER.'$/u', trim($line), $match) === 1
                    ? $buttons[(int) $match[1]] ?? null
                    : null;
                if ($button !== null) {
                    $html .= $this->paragraph($lines);
                    $lines = [];
                    $buttonRow[] = $button;
                    continue;
                }
                $html .= $this->buttonRow($buttonRow);
                $buttonRow = [];
                $lines[] = $line;
            }
            $html .= $this->paragraph($lines).$this->buttonRow($buttonRow);
        }

        return $html;
    }

    /** @param list<string> $lines */
    private function paragraph(array $lines): string
    {
        $text = trim(implode("\n", $lines));

        if ($text === '') {
            return '';
        }
        $html = preg_replace('/'.self::BOLD_MARKER.'([^'.self::BOLD_MARKER.']+)'.self::BOLD_MARKER.'/u', '<strong>$1</strong>', $this->escape($text)) ?? $this->escape($text);

        return sprintf('<p style="margin:0 0 16px;">%s</p>', nl2br(str_replace([self::BOLD_MARKER, self::MARKER], '', $html), false));
    }

    /**
     * Der erste Button einer Reihe ist gefüllt, weitere sind umrandet. Ohne gültige http(s)-Adresse
     * erscheint die Zeile als Text, statt einen kaputten Button zu zeigen.
     *
     * @param list<array{label: string, url: string}> $buttons
     */
    private function buttonRow(array $buttons): string
    {
        if ($buttons === []) {
            return '';
        }
        $links = [];
        foreach ($buttons as $index => $button) {
            if (preg_match('~^https?://\S+$~', $button['url']) !== 1) {
                $links[] = sprintf('<span style="display:inline-block;margin:0 8px 8px 0;">%s: %s</span>', $this->escape($button['label']), $this->escape($button['url']));
                continue;
            }
            $links[] = sprintf(
                '<a href="%s" style="display:inline-block;margin:0 8px 8px 0;padding:11px 20px;border:2px solid %s;border-radius:999px;background-color:%s;color:%s;font-weight:bold;text-decoration:none;line-height:1.2;">%s</a>',
                $this->escape($button['url']),
                self::PRIMARY,
                $index === 0 ? self::PRIMARY : self::WHITE,
                $index === 0 ? self::WHITE : self::PRIMARY,
                $this->escape($button['label']),
            );
        }

        return sprintf('<p style="margin:4px 0 16px;">%s</p>', implode('', $links));
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
