<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds member_access_token.primary_member_number — der Zugangsantrag wird jetzt per '
            .'E-Mail + Geburtsdatum auf genau ein Mitglied gematcht und der Token an dessen Haushalt '
            .'(Hauptnummer) gebunden statt an die gesamte, ggf. mehrdeutige E-Mail-Adresse.';
    }

    public function up(Schema $schema): void
    {
        // Bestehende Einträge sind reine Kurzzeit-Token (30 Minuten gültig) ohne Bestandsschutz —
        // unbedenklich zu leeren, statt die neue Pflichtspalte nachträglich zu befüllen. Direkter
        // Connection-Zugriff statt addSql(), damit das DELETE sicher vor dem per $schema erzeugten
        // ALTER TABLE läuft (siehe Version20260907150000::down()).
        $this->connection->executeStatement('DELETE FROM member_access_token');

        $schema->getTable('member_access_token')->addColumn('primary_member_number', 'string', ['length' => 20]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('member_access_token')->dropColumn('primary_member_number');
    }
}
