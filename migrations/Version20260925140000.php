<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_terms (Preis je Buchung pro Bezugsdauer, Gruppengröße min./max.) '
            .'sauna_booking.person_count/price_cents (Personenzahl und Preis-Momentaufnahme je Anfrage) and '
            .'sauna_season.closed_on (Saisonabschluss unabhängig vom geplanten Enddatum).';
    }

    public function up(Schema $schema): void
    {
        $terms = $schema->createTable('sauna_terms');
        $terms->addColumn('id', 'string', ['length' => 36]);
        $terms->addColumn('price_cents', 'integer');
        $terms->addColumn('price_unit_minutes', 'integer');
        $terms->addColumn('min_persons', 'smallint');
        $terms->addColumn('max_persons', 'smallint');
        $terms->addColumn('updated_at', 'datetime_immutable');
        $terms->setPrimaryKey(['id']);

        $booking = $schema->getTable('sauna_booking');
        // Defaults nur für eventuell bereits vorhandene Anfragen aus der Zeit vor den Konditionen.
        $booking->addColumn('person_count', 'smallint', ['default' => 2]);
        $booking->addColumn('price_cents', 'integer', ['default' => 0]);

        $schema->getTable('sauna_season')->addColumn('closed_on', 'date_immutable', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('sauna_season')->dropColumn('closed_on');
        $booking = $schema->getTable('sauna_booking');
        $booking->dropColumn('price_cents');
        $booking->dropColumn('person_count');
        $schema->dropTable('sauna_terms');
    }
}
