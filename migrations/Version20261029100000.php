<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds sauna_duty_waiver (Sauna-Dienstplan: „kein Dienst nötig“ je Zeitpunkt oder ganzer Tag).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sauna_duty_waiver (id VARCHAR(36) NOT NULL, waiver_date DATE NOT NULL, waiver_time VARCHAR(5) DEFAULT NULL, '
            .'INDEX idx_sauna_duty_waiver_date (waiver_date), '
            .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sauna_duty_waiver');
    }
}
