<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Meine Mitgliedschaft: Zugang auf den Zahler und die von ihm bezahlten Mitglieder beschränken (member_access_token.payer_member_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_access_token ADD payer_member_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_access_token DROP payer_member_id');
    }
}
