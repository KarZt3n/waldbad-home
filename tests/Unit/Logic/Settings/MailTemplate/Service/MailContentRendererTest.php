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

    public function testButtonsWithoutWebAddressAreShownAsText(): void
    {
        $renderer = new MailContentRenderer();
        [$text, $buttons] = $renderer->prepareTemplate("Text\n[[Öffnen|javascript:alert(1)]]");

        self::assertSame(
            '<p style="margin:0 0 16px;">Text</p><p style="margin:4px 0 16px;"><span style="display:inline-block;margin:0 8px 8px 0;">Öffnen: javascript:alert(1)</span></p>',
            $renderer->toHtmlFragment($text, $buttons),
        );
    }

    public function testKeepsSingleLineBreaksWithinAParagraph(): void
    {
        $html = (new MailContentRenderer())->toHtmlFragment("Zeile eins\nZeile zwei");

        self::assertSame("<p style=\"margin:0 0 16px;\">Zeile eins<br>\nZeile zwei</p>", $html);
    }
}
