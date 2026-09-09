<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the mail_template table for the new „Mailvorlagen" tab (editable subject/body '
            .'text behind every automatically sent email), seeded with the default texts for the '
            .'two currently existing templates.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('mail_template');
        $table->addColumn('template_key', 'string', ['length' => 60]);
        $table->addColumn('subject', 'string', ['length' => 255]);
        $table->addColumn('body', 'text');
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['template_key']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('mail_template');
    }

    public function postUp(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen. Die Standardtexte werden
        // bewusst dupliziert (nicht aus MailTemplateKey gelesen) — Migrationen bleiben so ein
        // stabiler, von späteren Code-Änderungen unabhängiger Schnappschuss.
        $this->connection->executeStatement(
            'INSERT INTO mail_template (template_key, subject, body, updated_at) VALUES (:key, :subject, :body, NULL)',
            [
                'key' => 'membership_application_submitted_notification',
                'subject' => 'Neuer Mitgliedsantrag eingegangen',
                'body' => <<<'TEXT'
                    {{vorname}} {{nachname}} hat einen Mitgliedsantrag gestellt ({{mitgliedschaftsart}}).

                    Bitte im Admin-Bereich unter „Mitgliederverwaltung“ → „Mitgliedsanträge“ prüfen.
                    TEXT,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO mail_template (template_key, subject, body, updated_at) VALUES (:key, :subject, :body, NULL)',
            [
                'key' => 'membership_application_approved',
                'subject' => 'Willkommen im {{vereinsname}} – deine Mitgliedschaft ist bestätigt',
                'body' => <<<'TEXT'
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
                    TEXT,
            ],
        );
    }
}
