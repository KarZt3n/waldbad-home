<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds sauna_booking_approval_token (Link „Anfrage annehmen“ aus der Benachrichtigungsmail).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sauna_booking_approval_token (id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, '
            .'booking_ids JSON NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, '
            .'UNIQUE INDEX uniq_sauna_booking_approval_token_hash (token_hash), '
            .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sauna_booking_approval_token');
    }
}
