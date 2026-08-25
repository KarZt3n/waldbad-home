<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824110000 extends AbstractMigration
{
    private const ALL_MODULES = '["pages","activities","guestbook","contact_requests","event_helpers","membership_applications","user_management"]';

    public function getDescription(): string
    {
        return 'Adds module-based access rights to CMS users.';
    }

    public function up(Schema $schema): void
    {
        $users = $schema->getTable('cms_user');
        $users->addColumn('modules', 'json', ['default' => self::ALL_MODULES]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('cms_user')->dropColumn('modules');
    }
}
