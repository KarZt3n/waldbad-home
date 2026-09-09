<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909160000 extends AbstractMigration
{
    private const string OLD_BODY = <<<'TEXT'
        Hallo {{vorname}} {{nachname}},

        herzlich willkommen im {{vereinsname}}! Dein Mitgliedsantrag wurde angenommen — du bist ab dem {{beitrittsdatum}} Mitglied (Mitgliedsnummer {{mitgliedsnummer}}).

        Deine Mitgliedskarte(n) können vor Ort im Waldbad abgeholt werden.

        Übersicht der angemeldeten Personen:
        {{personen}}

        Beiträge:
        {{beitraege}}

        Bei Fragen melde dich gerne bei uns.

        Viele Grüße
        Dein {{vereinsname}}
        TEXT;

    private const string NEW_BODY = <<<'TEXT'
        Hallo {{vorname}} {{nachname}},

        herzlich willkommen im {{vereinsname}}! Dein Mitgliedsantrag wurde angenommen — du bist ab dem {{beitrittsdatum}} Mitglied (Mitgliedsnummer {{mitgliedsnummer}}).

        Deine Mitgliedskarte(n) können vor Ort im Waldbad abgeholt werden.

        Übersicht der angemeldeten Personen:
        {{personen}}

        Beiträge:

        {{beitraege}}

        Bei Fragen melde dich gerne bei uns.

        Viele Grüße
        Dein {{vereinsname}}
        TEXT;

    public function getDescription(): string
    {
        return 'Fügt in der von Version20260909150000 eingefügten Standard-Vorlage '
            .'"membership_application_approved" eine Leerzeile zwischen "Beiträge:" und '
            .'"{{beitraege}}" ein, damit die Beitragsübersicht in der HTML-Ansicht der Mail als '
            .'eigener Absatz (und damit als echte Liste statt eines unersetzt sichtbaren Platzhalters) '
            .'erkannt wird. Betrifft nur den unveränderten Auslieferungszustand — eine bereits von '
            .'einem Admin angepasste Vorlage wird nicht überschrieben.';
    }

    public function up(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() bräuchte einen eigenen Schema-Change,
        // den es hier nicht gibt — up() reicht für eine reine Datenkorrektur.
        $this->connection->executeStatement(
            'UPDATE mail_template SET body = :newBody WHERE template_key = :key AND body = :oldBody',
            ['key' => 'membership_application_approved', 'oldBody' => self::OLD_BODY, 'newBody' => self::NEW_BODY],
        );
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement(
            'UPDATE mail_template SET body = :oldBody WHERE template_key = :key AND body = :newBody',
            ['key' => 'membership_application_approved', 'oldBody' => self::OLD_BODY, 'newBody' => self::NEW_BODY],
        );
    }
}
