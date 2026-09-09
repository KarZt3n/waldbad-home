<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use PHPUnit\Framework\TestCase;

final class MailContentRendererTest extends TestCase
{
    public function testSplitsBlankLinesIntoSeparateEscapedParagraphs(): void
    {
        $html = (new MailContentRenderer())->toHtmlFragment("Erster Absatz\n\nZweiter Absatz <b>fett</b>");

        self::assertSame(
            '<p style="margin:0 0 16px;">Erster Absatz</p><p style="margin:0 0 16px;">Zweiter Absatz &lt;b&gt;fett&lt;/b&gt;</p>',
            $html,
        );
    }

    public function testKeepsSingleLineBreaksWithinAParagraph(): void
    {
        $html = (new MailContentRenderer())->toHtmlFragment("Zeile eins\nZeile zwei");

        self::assertSame("<p style=\"margin:0 0 16px;\">Zeile eins<br>\nZeile zwei</p>", $html);
    }

    /**
     * Ein Absatz, der (getrimmt) exakt einem Schlüssel aus `$rawBlocks` entspricht, wird durch das
     * dort hinterlegte, bereits fertige HTML ersetzt — unescaped und ohne `<p>`-Wrapper, siehe
     * `ReleaseMembershipApplicationUseCase::formatContributionsAsHtml()`.
     */
    public function testReplacesAParagraphMatchingARawBlockKeyVerbatim(): void
    {
        $html = (new MailContentRenderer())->toHtmlFragment(
            "Beiträge:\n\n{{beitraege}}\n\nDanke.",
            ['{{beitraege}}' => '<ul><li>Position</li></ul>'],
        );

        self::assertSame(
            '<p style="margin:0 0 16px;">Beiträge:</p><ul><li>Position</li></ul><p style="margin:0 0 16px;">Danke.</p>',
            $html,
        );
    }

    /**
     * Steht der Token nicht allein in seinem Absatz — z. B. weil eine bereits gespeicherte Vorlage
     * „Beiträge:“ und den Platzhalter ohne trennende Leerzeile auf zwei Zeilen desselben Absatzes
     * stehen hat — wird trotzdem korrekt ersetzt, statt sichtbar unersetzt in der Mail zu bleiben
     * (echter, zuvor gemeldeter Fehler: der Token blieb dort wörtlich als „{{beitraege}}“ stehen).
     */
    public function testReplacesARawBlockKeyEvenWhenItIsNotAloneInItsParagraph(): void
    {
        $html = (new MailContentRenderer())->toHtmlFragment(
            "Beiträge:\n{{beitraege}}",
            ['{{beitraege}}' => '<ul><li>Position</li></ul>'],
        );

        self::assertSame(
            "<p style=\"margin:0 0 16px;\">Beiträge:<br>\n<ul><li>Position</li></ul></p>",
            $html,
        );
    }
}
