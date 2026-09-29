<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929100000 extends AbstractMigration
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

        Deine Beiträge und weitere Angaben zu deiner Mitgliedschaft kannst du jederzeit unter „Meine Mitgliedschaft“ einsehen. Dort forderst du mit deiner E-Mail-Adresse und deinem Geburtsdatum einen Zugang an:
        {{link}}

        Bei Fragen melde dich gerne bei uns.

        Viele Grüße
        Dein {{vereinsname}}
        TEXT;

    public function getDescription(): string
    {
        return 'Ersetzt in der Standard-Vorlage "membership_application_approved" die Beitragsübersicht '
            .'({{beitraege}}) durch einen Verweis auf "Meine Mitgliedschaft" ({{link}}). Betrifft nur den '
            .'unveränderten Auslieferungszustand — eine bereits von einem Admin angepasste Vorlage wird '
            .'nicht überschrieben.';
    }

    public function up(Schema $schema): void
    {
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
