<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vermietung/Sauna: adds sauna_booking.request_id (gemeinsame Kennung der je Wunschtag angelegten '
            .'Buchungen einer individuellen Anfrage) and sauna_booking_participant (Namen der Personen).';
    }

    public function up(Schema $schema): void
    {
        $booking = $schema->getTable('sauna_booking');
        $booking->addColumn('request_id', 'string', ['length' => 36, 'notnull' => false]);
        $booking->addIndex(['request_id'], 'idx_sauna_booking_request');

        $participant = $schema->createTable('sauna_booking_participant');
        $participant->addColumn('id', 'string', ['length' => 36]);
        $participant->addColumn('booking_id', 'string', ['length' => 36]);
        $participant->addColumn('position', 'smallint');
        $participant->addColumn('first_name', 'string', ['length' => 120]);
        $participant->addColumn('last_name', 'string', ['length' => 120]);
        $participant->setPrimaryKey(['id']);
        $participant->addIndex(['booking_id', 'position'], 'idx_sauna_booking_participant_booking_position');
        $participant->addForeignKeyConstraint('sauna_booking', ['booking_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sauna_booking_participant');
        $booking = $schema->getTable('sauna_booking');
        $booking->dropIndex('idx_sauna_booking_request');
        $booking->dropColumn('request_id');
    }
}
