<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_season (Saisonzeitraum mit optionalem Ende und Dauer einer '
            .'Buchungseinheit), sauna_season_opening_hours (Wochenplan Mo–So) and sauna_booking '
            .'(öffentliche Sauna-Anmeldungen mit Status offen/angenommen/abgelehnt und optionaler '
            .'Mitgliederzuordnung).';
    }

    public function up(Schema $schema): void
    {
        $season = $schema->createTable('sauna_season');
        $season->addColumn('id', 'string', ['length' => 36]);
        $season->addColumn('name', 'string', ['length' => 120]);
        $season->addColumn('starts_on', 'date_immutable');
        $season->addColumn('ends_on', 'date_immutable', ['notnull' => false]);
        $season->addColumn('slot_duration_minutes', 'integer');
        $season->addColumn('created_at', 'datetime_immutable');
        $season->addColumn('updated_at', 'datetime_immutable');
        $season->setPrimaryKey(['id']);
        $season->addIndex(['starts_on'], 'idx_sauna_season_starts_on');

        $hours = $schema->createTable('sauna_season_opening_hours');
        $hours->addColumn('id', 'string', ['length' => 36]);
        $hours->addColumn('season_id', 'string', ['length' => 36]);
        $hours->addColumn('position', 'integer');
        $hours->addColumn('weekday', 'smallint');
        $hours->addColumn('start_time', 'string', ['length' => 5]);
        $hours->addColumn('end_time', 'string', ['length' => 5]);
        $hours->setPrimaryKey(['id']);
        $hours->addIndex(['season_id', 'position'], 'idx_sauna_season_opening_hours_season_position');
        $hours->addForeignKeyConstraint('sauna_season', ['season_id'], ['id'], ['onDelete' => 'CASCADE']);

        $booking = $schema->createTable('sauna_booking');
        $booking->addColumn('id', 'string', ['length' => 36]);
        $booking->addColumn('booking_date', 'date_immutable');
        $booking->addColumn('start_time', 'string', ['length' => 5]);
        $booking->addColumn('end_time', 'string', ['length' => 5]);
        $booking->addColumn('first_name', 'string', ['length' => 120]);
        $booking->addColumn('last_name', 'string', ['length' => 120]);
        $booking->addColumn('birth_date', 'date_immutable');
        $booking->addColumn('email', 'string', ['length' => 180, 'notnull' => false]);
        $booking->addColumn('message', 'text');
        $booking->addColumn('status', 'string', ['length' => 20]);
        $booking->addColumn('member_id', 'string', ['length' => 36, 'notnull' => false]);
        $booking->addColumn('member_number', 'string', ['length' => 20, 'notnull' => false]);
        $booking->addColumn('submitted_at', 'datetime_immutable');
        $booking->addColumn('updated_at', 'datetime_immutable');
        $booking->setPrimaryKey(['id']);
        $booking->addIndex(['booking_date', 'start_time'], 'idx_sauna_booking_date_start');
        $booking->addIndex(['status'], 'idx_sauna_booking_status');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sauna_booking');
        $schema->dropTable('sauna_season_opening_hours');
        $schema->dropTable('sauna_season');
    }
}
