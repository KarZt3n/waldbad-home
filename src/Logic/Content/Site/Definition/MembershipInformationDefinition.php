<?php

namespace App\Logic\Content\Site\Definition;

use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;

final class MembershipInformationDefinition
{
    public const CONSENT_HEADING = 'Einwilligungserklärung';

    /**
     * @return list<ContentBlock>
     */
    public static function blocks(): array
    {
        return [
            new ContentBlock(ContentBlockType::Heading, self::CONSENT_HEADING),
            new ContentBlock(
                ContentBlockType::RichText,
                '<p>Mit dem Beitritt eines Mitgliedes nimmt der „Naturbad Borkheide e.V.“ personenbezogene Daten zur Mitgliederverwaltung auf. Die zu diesem Zwecke erhobenen Daten werden unter Einhaltung der EU-Datenschutzgrundverordnung (DSGVO) auf EDV-Systemen gespeichert. Die Daten werden durch geeignete technische und organisatorische Maßnahmen vor der Kenntnisnahme Dritter geschützt. Die Daten werden nicht zu anderen Zwecken genutzt, weitergeleitet oder übermittelt. Sie werden bei Beendigung der Mitgliedschaft, bzw. bei Widerruf dieser Einwilligungserklärung unverzüglich gelöscht, sofern keine offenen Forderungen des Vereins bestehen.</p>'
                .'<ul>'
                .'<li><strong>Mit der Bekanntgabe der Mailadresse stimme ich dem Erhalt von E-Mails zu, ansonsten informiere ich mich auf der Internetseite des Vereins.</strong></li>'
                .'<li><strong>Datenänderungen an Adresse, Telefon oder E-Mail gebe ich dem Verein umgehend und unaufgefordert bekannt.</strong></li>'
                .'<li><strong>Nach Vollendung des 21. Lebensjahres werden Familienmitglieder ohne fristgemäße Kündigung automatisch Einzelmitglieder (Beitragsordnung).</strong></li>'
                .'<li><strong>Bei Verlust der Eintrittskarte für Vereinsmitglieder sind 10,00 € zu entrichten!</strong></li>'
                .'<li><strong>Bei missbräuchlicher Benutzung der Karte wird ein Entgelt von 40,00 € erhoben (siehe Haus- und Nutzungsordnung §1, Abs. 1)!</strong></li>'
                .'<li><strong>Mit meiner Unterschrift (bei Minderjährigen Unterschrift des gesetzlichen Vertreters!) bestätige ich die Kenntnisnahme aller vorgenannten Punkte und willige in die Verarbeitung nachfolgender personenbezogener Daten ein:</strong></li>'
                .'</ul>',
            ),
            new ContentBlock(ContentBlockType::Heading, 'Beitragsordnung des Naturbad Borkheide e.V.'),
            new ContentBlock(
                ContentBlockType::RichText,
                '<table><thead><tr><th scope="col">Personenkreis</th><th scope="col">Jahresmitgliedsbeitrag</th><th scope="col">Bemerkungen</th></tr></thead><tbody>'
                .'<tr><td>Einzelperson bis 21 Jahre</td><td>30,00 €</td><td></td></tr>'
                .'<tr><td>Einzelperson über 21 Jahre</td><td>50,00 €</td><td></td></tr>'
                .'<tr><td>Familie</td><td>20 % Rabatt auf den Mitgliedsbeitrag der Einzelpersonen</td><td>- gilt für Mutter und/oder Vater, wenn mindestens ein eigenes leibliches/adoptiertes Kind unter 21 Jahre ist, Mutter und/oder Vater Vereinsmitglieder sind und nur für diesen Personenkreis, Kinder &lt; 4 Jahre sind vom Beitrag befreit<br><br>- das dritte und jedes weitere Kind dieser Familie bis 21 Jahre, ist vom Mitgliedsbeitrag befreit</td></tr>'
                .'<tr><td>alle neuen Mitglieder,<br>einmalige Beitrittsgebühr</td><td>Familien 20,00 €<br>Einzelpersonen 10,00 €</td><td></td></tr>'
                .'</tbody></table>'
                .'<p><strong>Alle Vereinsmitglieder im Alter von 8 bis 65 Jahren zahlen zzgl. zum Mitgliedsbeitrag 15,00 Euro pro Jahr, die nach Ableistung von 5 Stunden gemeinnütziger Arbeit im Verein unmittelbar wieder ausgezahlt werden!</strong></p>'
                .'<p><small>Stand: Januar 2026</small></p>',
            ),
        ];
    }
}
