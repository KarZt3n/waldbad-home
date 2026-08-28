<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional page-specific editor and publisher permissions to CMS users.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('cms_user')->addColumn('page_access', 'json', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('cms_user')->dropColumn('page_access');
    }
}
