<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds work_assignment_refund_record (Gutschrift-Historie der Arbeitsstunden-Überweisungen).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE work_assignment_refund_record (id VARCHAR(36) NOT NULL, primary_member_number VARCHAR(20) NOT NULL, '
            .'payer_member_id VARCHAR(36) NOT NULL, refund_year INT NOT NULL, amount_cents INT NOT NULL, message_id VARCHAR(35) NOT NULL, '
            .'exported_at DATETIME NOT NULL, INDEX idx_work_assignment_refund_record_year (refund_year, primary_member_number), '
            .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE work_assignment_refund_record');
    }
}
