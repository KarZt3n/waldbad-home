<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * „Flexible Unterstützung“ ist beim Öffnen des Helferformulars bereits angehakt.
 */
final class Version20261008140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a "preselected" flag to the activity catalog so an activity is checked by default in the public helper form.';
    }

    public function up(Schema $schema): void
    {
        // Wie Version20260831130000: temporärer Default zum Befüllen, danach entfernt.
        $this->addSql('ALTER TABLE event_activity ADD preselected TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_activity ALTER preselected DROP DEFAULT');
        $this->addSql("UPDATE event_activity SET preselected = 1 WHERE name LIKE 'Flexible Unterst%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_activity DROP preselected');
    }
}
