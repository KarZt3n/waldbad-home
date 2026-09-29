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
}
