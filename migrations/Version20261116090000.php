<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds member.deleted_at (Soft-Delete für Mitglieder).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_member_deleted_at ON member (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        // Als gelöscht markierte Mitglieder würden nach dem Entfernen der Spalte wieder sichtbar —
        // daher werden sie beim Zurückrollen endgültig gelöscht (wie vor dem Soft-Delete).
        $this->addSql('DELETE FROM member WHERE deleted_at IS NOT NULL');
        $this->addSql('DROP INDEX idx_member_deleted_at ON member');
        $this->addSql('ALTER TABLE member DROP deleted_at');
    }
}
