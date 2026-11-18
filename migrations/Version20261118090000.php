<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kündigungen aus „Meine Mitgliedschaft“ (member_cancellation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE member_cancellation (
                id VARCHAR(36) NOT NULL,
                member_id VARCHAR(36) NOT NULL,
                member_number VARCHAR(20) NOT NULL,
                member_name VARCHAR(240) NOT NULL,
                primary_member_number VARCHAR(20) NOT NULL,
                requested_by_email VARCHAR(180) NOT NULL,
                requested_by_name VARCHAR(240) DEFAULT NULL,
                requested_by_member_number VARCHAR(20) DEFAULT NULL,
                submitted_at DATETIME NOT NULL,
                exit_date DATE NOT NULL,
                successor_member_id VARCHAR(36) DEFAULT NULL,
                successor_name VARCHAR(240) DEFAULT NULL,
                contact_recipient_member_id VARCHAR(36) DEFAULT NULL,
                contact_recipient_name VARCHAR(240) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                decided_at DATETIME DEFAULT NULL,
                INDEX idx_member_cancellation_status (status),
                INDEX idx_member_cancellation_member (member_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_cancellation');
    }
}
