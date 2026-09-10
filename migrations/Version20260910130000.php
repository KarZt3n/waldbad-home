<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the optional signature_id column to mail_template — a Mailvorlage kann jetzt '
            .'eine wiederverwendbare Signatur (mail_signature) referenzieren, statt deren Text im '
            .'Vorlagentext zu duplizieren. Keine Fremdschlüssel-Constraint: läuft absichtlich einfach '
            .'ins Leere, wenn die referenzierte Signatur später gelöscht wird (siehe '
            .'MailTemplateRenderer).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('mail_template')->addColumn('signature_id', 'string', ['length' => 36, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('mail_template')->dropColumn('signature_id');
    }
}
