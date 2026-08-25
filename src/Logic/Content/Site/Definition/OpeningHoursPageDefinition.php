<?php

namespace App\Logic\Content\Site\Definition;

use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;

final class OpeningHoursPageDefinition
{
    public const TITLE = 'Öffnungszeiten und Eintritt';
    public const SEO_TITLE = 'Öffnungszeiten & Eintrittspreise';
    public const SLUG = 'oeffnungszeiten-und-eintritt';
    public const NAVIGATION_LABEL = 'Öffnungszeiten & Preise';
    public const SEO_DESCRIPTION = 'Aktuelle Öffnungszeiten, Eintrittspreise, Wochenkarten und Hinweise zum Kassenautomaten und Einlass im Waldbad Borkheide.';

    /**
     * @return list<ContentBlock>
     */
    public static function blocks(): array
    {
        return [
            new ContentBlock(ContentBlockType::Heading, 'Öffnungszeiten, Preise und Eintritt'),
            new ContentBlock(ContentBlockType::Alert, 'Wir haben täglich von 09-20 Uhr geöffnet, letzter Einlass 19:30 Uhr!'),
            new ContentBlock(
                ContentBlockType::RichText,
                '<table><tbody>'
                .'<tr><td><strong>Tagespreis</strong><br>ab 17 Uhr</td><td><strong>5,00 Euro</strong><br>3,00 Euro</td></tr>'
                .'<tr><td><strong>Wochenkarte</strong></td><td>20,00 Euro</td></tr>'
                .'<tr><td><strong>Wochenendkarte</strong><br>Samstag 09 Uhr bis Sonntag 20 Uhr<br>ab Freitag kaufbar!</td><td>8,00 Euro</td></tr>'
                .'</tbody></table>',
            ),
            new ContentBlock(
                ContentBlockType::RichText,
                '<p>Mit den Wochen- und Wochenendkarten ist ein mehrmaliger Eintritt am Tag möglich, allerdings sind sie nach Zutritt für 60 Minuten gesperrt. Tageskarten gelten nur für einen Eintritt!</p>'
                .'<p>Die Beiträge für Vereinsmitglieder erfahren Sie im Mitgliedsantrag!</p>',
            ),
            new ContentBlock(ContentBlockType::Heading, 'Der Eintritt ins Bad'),
            new ContentBlock(
                ContentBlockType::RichText,
                '<p>Liebe Gäste,</p>'
                .'<p>unser Bad hat keinen personell besetzen Eingang, das erledigt ein Kassenautomat mit Eingangsterminal vor dem Drehkreuz.</p>'
                .'<p>Am Automat kann man sich ein Tagesticket ziehen, welches für den einmaligen Eintritt gültig und damit entwertet ist.</p>'
                .'<p>Ab 3 Jahre ist eine eigene Eintrittskarte nötig. Das Drehkreuz darf pro Drehung nur jeweils mit einer Person benutzt werden.</p>'
                .'<p>Nach Möglichkeit das Eintrittsgeld passend zahlen, da Geldscheine je nach Eintrittssumme gestaffelt angenommen werden (5 Euro - 10 Euroschein, 10 Euro - 20 Euroschein, 29 Euro - 50 Euroschein).</p>'
                .'<p>Beispiel 1: Sie kommen alleine ins Bad, 1x Tageskarte anklicken, Bezahlen ist nun mit Kleingeld, sowie 5 oder 10 Euroschein möglich.</p>'
                .'<p>Beispiel 2: Sie sind zu dritt oder mehr, durch mehrmaliges Klicken des Feldes Tageskarte die Personenanzahl wählen, ab 9 Euro Eintritt wird der 20 Euroschein im Display angezeigt.</p>'
                .'<p>Wenn Sie mit einem Kinderwagen in das Bad möchten, nutzen Sie bitte die Klingel rechts neben dem Tor. Das Ticket geben Sie bitte beim Rettungsschwimmer ab (die restlichen Personen gehen normal durch das Drehkreuz).</p>',
            ),
        ];
    }
}
