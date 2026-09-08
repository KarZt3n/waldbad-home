<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allows members without own bank details (e.g. family dependents paid by another '
            .'member) and switches new member numbers to the "Bad-XXXXX" format continuing the '
            .'historical numbering from the previous membership administration.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('member');
        $table->getColumn('account_holder')->setNotnull(false);
        $table->getColumn('iban')->setNotnull(false);
        $table->getColumn('mandate_reference')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('member');
        $table->getColumn('mandate_reference')->setNotnull(true);
        $table->getColumn('iban')->setNotnull(true);
        $table->getColumn('account_holder')->setNotnull(true);
    }

    /**
     * Die reale Bestandsverwaltung vergibt Mitgliedsnummern im Format „Bad-01234“, nicht wie
     * ursprünglich angenommen „M-0001“. Der Generator wird entsprechend umgestellt (siehe
     * DoctrineMemberNumberGenerator); die Sequenz wird hier auf den nächsten freien Wert nach der
     * höchsten importierten Bestandsnummer (Bad-03064) gesetzt, damit neu angelegte Mitglieder
     * nicht mit importierten Nummern kollidieren.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement('UPDATE member_number_sequence SET next_value = 3065 WHERE id = 1');
    }

    public function postDown(Schema $schema): void
    {
        $this->connection->executeStatement('UPDATE member_number_sequence SET next_value = 1 WHERE id = 1');
    }
}
