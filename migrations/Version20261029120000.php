<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds sauna_booking.duty_required (Sauna-Dienst für die Buchung nötig, Standard ja).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sauna_booking ADD duty_required TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sauna_booking DROP duty_required');
    }
}
