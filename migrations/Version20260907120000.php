<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds members, their remarks and the member number sequence for the Mitgliederverwaltung module.';
    }

    public function up(Schema $schema): void
    {
        $member = $schema->createTable('member');
        $member->addColumn('id', 'string', ['length' => 36]);
        $member->addColumn('member_number', 'string', ['length' => 20]);
        $member->addColumn('primary_member_number', 'string', ['length' => 20]);
        $member->addColumn('salutation', 'string', ['length' => 20]);
        $member->addColumn('last_name', 'string', ['length' => 120]);
        $member->addColumn('first_name', 'string', ['length' => 120]);
        $member->addColumn('birth_date', 'date_immutable');
        $member->addColumn('street', 'string', ['length' => 180]);
        $member->addColumn('postal_code', 'string', ['length' => 5]);
        $member->addColumn('city', 'string', ['length' => 180]);
        $member->addColumn('email', 'string', ['length' => 180, 'notnull' => false]);
        $member->addColumn('phone', 'string', ['length' => 60, 'notnull' => false]);
        $member->addColumn('family_role', 'string', ['length' => 20]);
        $member->addColumn('joined_at', 'date_immutable');
        $member->addColumn('left_at', 'date_immutable', ['notnull' => false]);
        $member->addColumn('active', 'boolean');
        $member->addColumn('function', 'string', ['length' => 20]);
        $member->addColumn('account_holder', 'string', ['length' => 180]);
        $member->addColumn('iban', 'text');
        $member->addColumn('bank_name', 'string', ['length' => 180, 'notnull' => false]);
        $member->addColumn('mandate_reference', 'string', ['length' => 60]);
        $member->addColumn('payment_method', 'string', ['length' => 20]);
        $member->addColumn('payment_interval', 'string', ['length' => 20]);
        $member->addColumn('payment_day', 'string', ['length' => 20]);
        $member->addColumn('payer_type', 'string', ['length' => 20]);
        $member->addColumn('payer_member_id', 'string', ['length' => 36, 'notnull' => false]);
        $member->addColumn('next_booking_month', 'integer');
        $member->addColumn('next_booking_year', 'integer');
        $member->addColumn('contribution_category', 'string', ['length' => 30, 'notnull' => false]);
        $member->addColumn('contribution_amount_cents', 'integer', ['notnull' => false]);
        $member->addColumn('version', 'integer', ['default' => 1]);
        $member->addColumn('created_at', 'datetime_immutable');
        $member->addColumn('updated_at', 'datetime_immutable');
        $member->setPrimaryKey(['id']);
        $member->addUniqueIndex(['member_number'], 'uniq_member_number');
        $member->addIndex(['primary_member_number'], 'idx_member_primary_member_number');
        $member->addForeignKeyConstraint('member', ['payer_member_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_member_payer_member');

        $remark = $schema->createTable('member_remark');
        $remark->addColumn('id', 'string', ['length' => 36]);
        $remark->addColumn('member_id', 'string', ['length' => 36]);
        $remark->addColumn('text', 'text');
        $remark->addColumn('author_display_name', 'string', ['length' => 180, 'notnull' => false]);
        $remark->addColumn('created_at', 'datetime_immutable');
        $remark->setPrimaryKey(['id']);
        $remark->addIndex(['member_id', 'created_at'], 'idx_member_remark_member_created');
        $remark->addForeignKeyConstraint('member', ['member_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Einzeiliger Zähler für die Vergabe fortlaufender Mitgliedsnummern (Format „M-0001“).
        // Startwert ggf. vor dem Produktiv-Deploy an die höchste bereits vergebene Nummer der
        // bisherigen (externen) Mitgliederverwaltung anpassen.
        $sequence = $schema->createTable('member_number_sequence');
        $sequence->addColumn('id', 'integer');
        $sequence->addColumn('next_value', 'integer');
        $sequence->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('member_number_sequence');
        $schema->dropTable('member_remark');
        $schema->dropTable('member');
    }

    public function postUp(Schema $schema): void
    {
        // Direkt über die Connection statt addSql(): postUp() läuft nach dem Freeze der Migration,
        // addSql() würde dort eine FrozenMigration-Exception auslösen.
        $this->connection->executeStatement('INSERT INTO member_number_sequence (id, next_value) VALUES (1, 1)');
    }
}
