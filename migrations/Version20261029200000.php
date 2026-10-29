<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds membership_application.merged_into_member_number (Antrag in bestehende Mitgliedschaft übernommen).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership_application ADD merged_into_member_number VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership_application DROP merged_into_member_number');
    }
}
