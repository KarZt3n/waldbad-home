<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805194000 extends AbstractMigration
{
    private const string INDEX_NAME = 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750';

    public function getDescription(): string
    {
        return 'Aligns the Messenger transport index with the Symfony schema listener.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('messenger_messages')->renameIndex('idx_messenger_queue', self::INDEX_NAME);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('messenger_messages')->renameIndex(self::INDEX_NAME, 'idx_messenger_queue');
    }
}
