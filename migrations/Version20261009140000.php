<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds sauna_duty (Sauna-Dienstplan: wer zu welchem Einlass Dienst hat).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sauna_duty (id VARCHAR(36) NOT NULL, duty_date DATE NOT NULL, duty_time VARCHAR(5) NOT NULL, '
            .'member_id VARCHAR(36) NOT NULL, member_number VARCHAR(20) NOT NULL, first_name VARCHAR(120) NOT NULL, '
            .'last_name VARCHAR(120) NOT NULL, INDEX idx_sauna_duty_date_time (duty_date, duty_time), '
            .'UNIQUE INDEX uniq_sauna_duty_member (duty_date, duty_time, member_id), '
            .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sauna_duty');
    }
}
