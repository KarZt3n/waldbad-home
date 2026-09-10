<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\Model;

use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailTemplateKeyTest extends TestCase
{
    /**
     * Ein Platzhalter ohne Beispielwert würde in der Vorschau (siehe `PreviewMailTemplateUseCase`)
     * einfach unersetzt stehen bleiben, statt einen Fehler zu zeigen — dieser Test soll das trotzdem
     * auffangen, falls `placeholders()` künftig um einen Platzhalter erweitert wird, ohne
     * `samplePlaceholders()` entsprechend nachzuziehen.
     */
    #[DataProvider('keys')]
    public function testEverySamplePlaceholderMatchesTheDeclaredPlaceholders(MailTemplateKey $key): void
    {
        self::assertSame($key->placeholders(), array_keys($key->samplePlaceholders()));
    }

    #[DataProvider('keys')]
    public function testEverySampleHtmlBlockKeyIsADeclaredPlaceholder(MailTemplateKey $key): void
    {
        $htmlBlockKeys = array_keys($key->sampleHtmlBlocks());
        self::assertCount(count(array_intersect($htmlBlockKeys, $key->placeholders())), $htmlBlockKeys);
    }

    /**
     * @return list<array{MailTemplateKey}>
     */
    public static function keys(): array
    {
        return array_map(static fn (MailTemplateKey $key): array => [$key], MailTemplateKey::cases());
    }
}
