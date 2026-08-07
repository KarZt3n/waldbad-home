<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds frontend visibility control to CMS pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page ADD visible TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE cms_page ALTER visible DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page DROP COLUMN visible');
    }
}
