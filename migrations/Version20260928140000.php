<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mitgliederverwaltung: member.next_booking_month/next_booking_year nullable — Mitglieder, für die ein anderes Mitglied zahlt, haben keine eigene Buchung.';
    }

    public function up(Schema $schema): void
    {
        $member = $schema->getTable('member');
        $member->getColumn('next_booking_month')->setNotnull(false);
        $member->getColumn('next_booking_year')->setNotnull(false);
    }

    public function preDown(Schema $schema): void
    {
        $this->connection->executeStatement('UPDATE member SET next_booking_month = 3 WHERE next_booking_month IS NULL');
        $this->connection->executeStatement('UPDATE member SET next_booking_year = YEAR(joined_at) + 1 WHERE next_booking_year IS NULL');
    }

    public function down(Schema $schema): void
    {
        $member = $schema->getTable('member');
        $member->getColumn('next_booking_month')->setNotnull(true);
        $member->getColumn('next_booking_year')->setNotnull(true);
    }
}
