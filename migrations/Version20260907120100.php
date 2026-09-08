<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds contribution rates (Beitragssätze) for the Mitgliederverwaltung module, seeded from the Beitragsordnung.';
    }

    public function up(Schema $schema): void
    {
        $rate = $schema->createTable('contribution_rate');
        $rate->addColumn('id', 'string', ['length' => 36]);
        $rate->addColumn('category', 'string', ['length' => 30]);
        $rate->addColumn('label', 'string', ['length' => 180]);
        $rate->addColumn('amount_cents', 'integer');
        $rate->addColumn('period', 'string', ['length' => 20]);
        $rate->setPrimaryKey(['id']);
        $rate->addUniqueIndex(['category'], 'uniq_contribution_rate_category');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('contribution_rate');
    }

    /**
     * Startwerte gemäß „Beitrags- und Kassenordnung“, Anlage 1 (gültig ab 01.01.2025):
     * Einzelperson bis 21 Jahre 30,00 €, über 21 Jahre 50,00 €, Familie 20 % Rabatt (= 40,00 €
     * für Elternteile), Kinder < 4 Jahre bzw. ab dem 3. Kind beitragsfrei.
     */
    public function postUp(Schema $schema): void
    {
        $rows = [
            ['individual_junior', 'Einzelperson bis 21 Jahre', 3000],
            ['individual_senior', 'Einzelperson über 21 Jahre', 5000],
            ['family_adult', 'Familie: Elternteil', 4000],
            ['family_child_paying', 'Familie: Kind (zahlend)', 3000],
            ['family_child_exempt', 'Familie: Kind (beitragsfrei)', 0],
        ];
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen.
        foreach ($rows as [$category, $label, $amountCents]) {
            $this->connection->executeStatement(
                'INSERT INTO contribution_rate (id, category, label, amount_cents, period) VALUES (?, ?, ?, ?, ?)',
                [$this->generateId(), $category, $label, $amountCents, 'yearly'],
            );
        }
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
