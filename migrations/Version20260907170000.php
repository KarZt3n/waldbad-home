<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a configurable person group (Einzelperson/Familie) to contribution rates, a "once" payment period, one-time membership fee rates, and a per-member log of charged one-time fees.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('contribution_rate')->addColumn('person_group', 'string', ['length' => 20, 'notnull' => false]);

        $charge = $schema->createTable('member_contribution_charge');
        $charge->addColumn('id', 'string', ['length' => 36]);
        $charge->addColumn('member_id', 'string', ['length' => 36]);
        $charge->addColumn('label', 'string', ['length' => 180]);
        $charge->addColumn('amount_cents', 'integer');
        $charge->addColumn('charged_at', 'datetime_immutable');
        $charge->setPrimaryKey(['id']);
        $charge->addIndex(['member_id', 'charged_at'], 'idx_member_contribution_charge_member_charged');
        $charge->addForeignKeyConstraint('member', ['member_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        // Vor dem Entfernen der Spalte löschen, damit ein erneutes Hochmigrieren nicht dieselben
        // Beitrittsgebühren-Sätze doppelt anlegt (direkter Connection-Zugriff statt addSql(), siehe
        // Version20260907150000).
        $this->connection->executeStatement(
            "DELETE FROM contribution_rate WHERE category IS NULL AND label IN ('Beitrittsgebühr Einzelperson', 'Beitrittsgebühr Familie')",
        );

        $schema->dropTable('member_contribution_charge');
        $schema->getTable('contribution_rate')->dropColumn('person_group');
    }

    /**
     * Ordnet den bestehenden Grundkategorien ihren Personenkreis zu (rein informativ, die
     * automatische Berechnung nutzt weiterhin die feste Kategorie) und legt die einmalige
     * Beitrittsgebühr laut Beitragsordnung an: „alle neuen Mitglieder, einmalige Beitrittsgebühr:
     * Familien 20,00 €, Einzelpersonen 10,00 €“.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET person_group = 'individual' WHERE category IN ('individual_junior', 'individual_senior')",
        );
        $this->connection->executeStatement(
            "UPDATE contribution_rate SET person_group = 'family' WHERE category IN ('family_adult', 'family_child_paying', 'family_child_exempt')",
        );

        $this->connection->executeStatement(
            'INSERT INTO contribution_rate (id, category, label, amount_cents, period, person_group, min_age, max_age) '
            .'VALUES (?, NULL, ?, ?, ?, ?, NULL, NULL)',
            [$this->generateId(), 'Beitrittsgebühr Einzelperson', 1000, 'once', 'individual'],
        );
        $this->connection->executeStatement(
            'INSERT INTO contribution_rate (id, category, label, amount_cents, period, person_group, min_age, max_age) '
            .'VALUES (?, NULL, ?, ?, ?, ?, NULL, NULL)',
            [$this->generateId(), 'Beitrittsgebühr Familie', 2000, 'once', 'family'],
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
