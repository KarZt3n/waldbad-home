<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds site_branding (Logo in der Kopfzeile der Website).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_branding (id VARCHAR(20) NOT NULL, logo_url VARCHAR(500) DEFAULT NULL, '
            .'updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_branding');
    }
}
