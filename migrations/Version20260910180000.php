<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds event_help_request.is_member/email/birth_date/member_id (Zuordnung einer '
            .'Helferanmeldung zu einem Mitgliedsdatensatz für die Erstattung der '
            .'Arbeitszeit-Pauschale zum Jahresende).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('event_help_request');
        $table->addColumn('is_member', 'boolean', ['default' => false]);
        $table->addColumn('email', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('birth_date', 'date_immutable', ['notnull' => false]);
        $table->addColumn('member_id', 'string', ['length' => 36, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('event_help_request');
        $table->dropColumn('member_id');
        $table->dropColumn('birth_date');
        $table->dropColumn('email');
        $table->dropColumn('is_member');
    }
}
