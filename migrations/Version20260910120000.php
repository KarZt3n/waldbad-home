<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the mail_signature table for the new „Signaturen“ tab (wiederverwendbare '
            .'Signaturen zum Einfügen in Mailvorlagen), seeded with one default signature.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('mail_signature');
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('name', 'string', ['length' => 180]);
        $table->addColumn('body', 'text');
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('mail_signature');
    }

    public function postUp(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen.
        $this->connection->executeStatement(
            'INSERT INTO mail_signature (id, name, body) VALUES (:id, :name, :body)',
            [
                'id' => $this->generateId(),
                'name' => 'Standard-Signatur',
                'body' => <<<'TEXT'
                    Freundliche Grüße
                    Das Waldbad-Team
                    {{vereinsname}}
                    Kirchanger 14
                    14822 Borkheide
                    info@waldbad-borkheide.de
                    www.waldbad-borkheide.de
                    TEXT,
            ],
        );
    }

    private function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
