<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aktivitätszuordnungen werden im Helferformular standardmäßig nur noch aufgelistet; als Checkbox mit
 * Helferzahl erscheinen sie nur, wenn die Zuordnung „auswählbar“ ist. Bestehende Zuordnungen werden
 * auf reine Auflistung umgestellt – ausgenommen „Flexible Unterstützung“, die auch im Katalog als
 * standardmäßig auswählbar markiert wird.
 */
final class Version20261008100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a "selectable" flag to event schedule/template activity assignments (required_helpers becomes nullable) '
            .'and a "default selectable" flag to the activity catalog.';
    }

    public function up(Schema $schema): void
    {
        // Wie Version20260831130000: temporärer Default zum Befüllen, danach entfernt, damit die
        // Spalten exakt dem Entity-Mapping entsprechen (NOT NULL, kein Default).
        $this->addSql('ALTER TABLE event_activity ADD default_selectable TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_activity ALTER default_selectable DROP DEFAULT');
        $this->addSql("UPDATE event_activity SET default_selectable = 1 WHERE name LIKE 'Flexible Unterst%'");

        foreach (['event_schedule_activity', 'event_template_activity'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s ADD selectable TINYINT(1) DEFAULT 0 NOT NULL, MODIFY required_helpers INT DEFAULT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s ALTER selectable DROP DEFAULT', $table));
            $this->addSql(sprintf(
                'UPDATE %s SET selectable = 1 WHERE activity_id IN (SELECT id FROM event_activity WHERE default_selectable = 1)',
                $table,
            ));
            $this->addSql(sprintf('UPDATE %s SET required_helpers = NULL WHERE selectable = 0', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['event_schedule_activity', 'event_template_activity'] as $table) {
            $this->addSql(sprintf('UPDATE %s SET required_helpers = 1 WHERE required_helpers IS NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s DROP selectable, MODIFY required_helpers INT NOT NULL', $table));
        }
        $this->addSql('ALTER TABLE event_activity DROP default_selectable');
    }
}
