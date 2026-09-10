<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds member_access_token (zeitlich begrenzte Zugangs-Token für „Meine Mitgliedschaft") '
            .'and member_message (darüber gesendete Nachrichten an den Verein).';
    }

    public function up(Schema $schema): void
    {
        $token = $schema->createTable('member_access_token');
        $token->addColumn('id', 'string', ['length' => 36]);
        $token->addColumn('email', 'string', ['length' => 180]);
        $token->addColumn('token_hash', 'string', ['length' => 64]);
        $token->addColumn('expires_at', 'datetime_immutable');
        $token->setPrimaryKey(['id']);
        $token->addUniqueIndex(['token_hash'], 'uniq_member_access_token_hash');
        $token->addIndex(['expires_at'], 'idx_member_access_token_expires');

        $message = $schema->createTable('member_message');
        $message->addColumn('id', 'string', ['length' => 36]);
        $message->addColumn('member_id', 'string', ['length' => 36]);
        $message->addColumn('member_number', 'string', ['length' => 20]);
        $message->addColumn('member_name', 'string', ['length' => 240]);
        $message->addColumn('message', 'text');
        $message->addColumn('status', 'string', ['length' => 20]);
        $message->addColumn('submitted_at', 'datetime_immutable');
        $message->addColumn('updated_at', 'datetime_immutable');
        $message->setPrimaryKey(['id']);
        $message->addIndex(['status', 'submitted_at'], 'idx_member_message_status_submitted');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('member_message');
        $schema->dropTable('member_access_token');
    }
}
