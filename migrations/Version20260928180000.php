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
 *
 * Vorab erhalten die Lastschrift-Tabellen dieselbe Sortierung wie `member`: Je nach
 * MariaDB-Version legt der Server neue Tabellen mit einer anderen Standard-Sortierung an (z. B.
 * `utf8mb4_uca1400_ai_ci` statt `utf8mb4_unicode_520_ci`), und Vergleiche zwischen Spalten
 * unterschiedlicher Sortierung scheitern mit „Illegal mix of collations“.
 */
final class Version20260928180000 extends AbstractMigration
{
    private const string MESSAGE_ID = 'SAGE-UEBERNAHME';

    public function getDescription(): string
    {
        return 'Mitgliederverwaltung: aligns direct_debit_* collation with member and marks existing self-payer mandates (Sage) as already used.';
    }

    public function up(Schema $schema): void
    {
        $collation = $this->connection->fetchOne(
            "SELECT table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'member'",
        );
        $this->abortIf(!is_string($collation) || preg_match('/^utf8mb4_[a-z0-9_]+$/', $collation) !== 1, 'Die Sortierung der Tabelle member konnte nicht ermittelt werden.');
        foreach (['direct_debit_record', 'direct_debit_creditor'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s CONVERT TO CHARACTER SET utf8mb4 COLLATE %s', $table, $collation));
        }

        // Die Tabelle wurde in Version20260928160000 gerade erst angelegt und ist noch leer.
        $this->addSql(
            "INSERT INTO direct_debit_record (id, payer_member_id, mandate_reference, contribution_year, sequence_type, collection_date, amount_cents, message_id, exported_at)
             SELECT UUID(), m.id, m.mandate_reference, 2026, 'RCUR', CURRENT_DATE, 0, :messageId, CURRENT_TIMESTAMP
             FROM member m
             WHERE m.payer_type = 'self_payer'
               AND m.mandate_reference IS NOT NULL AND TRIM(m.mandate_reference) <> ''
               AND m.mandate_reference <> CONCAT('WV-', m.member_number, '-00001')",
            ['messageId' => self::MESSAGE_ID],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM direct_debit_record WHERE message_id = :messageId', ['messageId' => self::MESSAGE_ID]);
    }
}
