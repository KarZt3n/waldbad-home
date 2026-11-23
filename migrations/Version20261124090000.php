<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds footer_navigation_settings (Seiten im Footer der Website und deren Reihenfolge).';
    }

    public function up(Schema $schema): void
    {
        // Ohne Zeile gilt die bisherige Footer-Belegung (siehe `FooterNavigation::defaults()`).
        $this->addSql('CREATE TABLE footer_navigation_settings (id VARCHAR(20) NOT NULL, page_ids JSON NOT NULL, '
            .'updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE footer_navigation_settings');
    }
}
