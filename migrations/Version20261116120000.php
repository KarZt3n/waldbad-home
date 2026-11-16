<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Getrennte SEPA-Exporte: direct_debit_record.kind und direct_debit_collected_charge.';
    }

    public function up(Schema $schema): void
    {
        // Bestehende Einträge behalten kind = NULL: Sie enthielten Beitrag und Arbeitseinsatz-Pauschale gemeinsam.
        $this->addSql('ALTER TABLE direct_debit_record ADD kind VARCHAR(30) DEFAULT NULL');
        $this->addSql('CREATE TABLE direct_debit_collected_charge (charge_id VARCHAR(36) NOT NULL, record_id VARCHAR(36) DEFAULT NULL, collected_at DATETIME NOT NULL, PRIMARY KEY (charge_id)) DEFAULT CHARACTER SET utf8mb4');
        // Bisher wurden Einmalgebühren nicht als eingezogen vermerkt. Als eingezogen gelten Gebühren
        // aus Vorjahren sowie Gebühren, nach deren Berechnung für den Zahler bereits ein Export lief.
        $this->addSql(<<<'SQL'
            INSERT INTO direct_debit_collected_charge (charge_id, record_id, collected_at)
            SELECT charge.id, NULL, CURRENT_TIMESTAMP
            FROM member_contribution_charge charge
            INNER JOIN member ON member.id = charge.member_id
            WHERE YEAR(charge.charged_at) < YEAR(CURRENT_DATE)
               OR EXISTS (
                   SELECT 1 FROM direct_debit_record record
                   WHERE record.payer_member_id = CASE WHEN member.payer_type = 'other_member' AND member.payer_member_id IS NOT NULL THEN member.payer_member_id ELSE member.id END
                     AND record.message_id <> 'SAGE-UEBERNAHME'
                     AND record.exported_at >= charge.charged_at
               )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE direct_debit_collected_charge');
        $this->addSql('ALTER TABLE direct_debit_record DROP kind');
    }
}
