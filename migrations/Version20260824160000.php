<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes the transitional default from per-module CMS user access.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_user ALTER modules DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_user ALTER modules SET DEFAULT \'{}\'');
    }
}
