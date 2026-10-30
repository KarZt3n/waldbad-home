<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds social_media_settings (Social-Media-Leiste der Website).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE social_media_settings (id VARCHAR(20) NOT NULL, visible TINYINT NOT NULL, links JSON NOT NULL, '
            .'updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE social_media_settings');
    }
}
