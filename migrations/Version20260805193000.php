<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the Doctrine Messenger failure transport table.';
    }

    public function up(Schema $schema): void
    {
        $messages = $schema->createTable('messenger_messages');
        $messages->addColumn('id', 'bigint', ['autoincrement' => true]);
        $messages->addColumn('body', 'text');
        $messages->addColumn('headers', 'text');
        $messages->addColumn('queue_name', 'string', ['length' => 190]);
        $messages->addColumn('created_at', 'datetime');
        $messages->addColumn('available_at', 'datetime');
        $messages->addColumn('delivered_at', 'datetime', ['notnull' => false]);
        $messages->setPrimaryKey(['id']);
        $messages->addIndex(
            ['queue_name', 'available_at', 'delivered_at', 'id'],
            'idx_messenger_queue',
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('messenger_messages');
    }
}
