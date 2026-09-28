<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Markiert die Mandate aller bestehenden Selbstzahler als bereits genutzt: Sie wurden in Sage
 * eingezogen (Beitragsjahr 2026), der erste Export über die neue Verwaltung ist deshalb eine
 * Folgelastschrift, und für 2026 wird keine Lastschrift des Eintrittsjahres mehr vorgeschlagen.
 * Ausgenommen sind in der neuen Verwaltung erzeugte Mandate („WV-<Mitgliedsnummer>-00001“) —
 * deren erste Lastschrift bleibt eine Erstlastschrift.
 */
final class Version20260928180000 extends AbstractMigration
{
    private const string MESSAGE_ID = 'SAGE-UEBERNAHME';

    public function getDescription(): string
    {
        return 'Mitgliederverwaltung: marks existing self-payer mandates (Sage) as already used in direct_debit_record.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO direct_debit_record (id, payer_member_id, mandate_reference, contribution_year, sequence_type, collection_date, amount_cents, message_id, exported_at)
             SELECT UUID(), m.id, m.mandate_reference, 2026, 'RCUR', CURRENT_DATE, 0, :messageId, CURRENT_TIMESTAMP
             FROM member m
             WHERE m.payer_type = 'self_payer'
               AND m.mandate_reference IS NOT NULL AND TRIM(m.mandate_reference) <> ''
               AND m.mandate_reference <> CONCAT('WV-', m.member_number, '-00001')
               AND NOT EXISTS (SELECT 1 FROM direct_debit_record r WHERE r.payer_member_id = m.id AND r.mandate_reference = m.mandate_reference)",
            ['messageId' => self::MESSAGE_ID],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM direct_debit_record WHERE message_id = :messageId', ['messageId' => self::MESSAGE_ID]);
    }
}
