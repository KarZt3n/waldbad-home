<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the work_assignment_surcharge contribution rate (Arbeitseinsatz) with a configurable age range, and stores its computed amount on the member.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('contribution_rate')->addColumn('min_age', 'integer', ['notnull' => false]);
        $schema->getTable('contribution_rate')->addColumn('max_age', 'integer', ['notnull' => false]);
        $schema->getTable('member')->addColumn('work_assignment_surcharge_cents', 'integer', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        // Vor dem Entfernen der Spalten löschen, damit ein erneutes Hochmigrieren nicht an der
        // Unique-Constraint auf "category" scheitert (direkter Connection-Zugriff statt addSql(),
        // da down() für Datenänderungen sonst denselben Freeze-Mechanismus wie postUp() träfe).
        $this->connection->executeStatement("DELETE FROM contribution_rate WHERE category = 'work_assignment_surcharge'");

        $schema->getTable('member')->dropColumn('work_assignment_surcharge_cents');
        $schema->getTable('contribution_rate')->dropColumn('max_age');
        $schema->getTable('contribution_rate')->dropColumn('min_age');
    }

    /**
     * Zuschlag zzgl. zum Mitgliedsbeitrag laut Beitragsordnung: „Alle Vereinsmitglieder im Alter
     * von 8 bis 65 Jahren zahlen zzgl. zum Mitgliedsbeitrag 15,00 Euro pro Jahr, die nach
     * Ableistung von 5 Stunden gemeinnütziger Arbeit im Verein unmittelbar wieder ausgezahlt
     * werden.“ Altersspanne und Betrag sind im Admin unter „Beitragssätze" anpassbar.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(
            'INSERT INTO contribution_rate (id, category, label, amount_cents, period, age_threshold, min_age, max_age) '
            .'VALUES (?, ?, ?, ?, ?, NULL, ?, ?)',
            [$this->generateId(), 'work_assignment_surcharge', 'Arbeitseinsatz (Rückerstattung nach 5 Gemeinschaftsstunden)', 1500, 'yearly', 8, 65],
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
