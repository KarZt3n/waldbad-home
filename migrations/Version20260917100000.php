<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E-Mail-Einwilligung für Mitglieder: adds member.email_consent (aus dem Mitgliedsantrag '
            .'übernommen bzw. per Doppel-Opt-in bestätigt) and member_email_consent_token (per Mail '
            .'verschickte Bestätigungslinks, siehe SendMemberEmailConsentRequestUseCase, '
            .'ConfirmMemberEmailConsentUseCase).';
    }

    public function up(Schema $schema): void
    {
        $member = $schema->getTable('member');
        $member->addColumn('email_consent', 'boolean', ['default' => false]);

        $token = $schema->createTable('member_email_consent_token');
        $token->addColumn('id', 'string', ['length' => 36]);
        $token->addColumn('member_id', 'string', ['length' => 36]);
        $token->addColumn('email', 'string', ['length' => 180]);
        $token->addColumn('token_hash', 'string', ['length' => 64]);
        $token->addColumn('expires_at', 'datetime_immutable');
        $token->addColumn('confirmed_at', 'datetime_immutable', ['notnull' => false]);
        $token->setPrimaryKey(['id']);
        $token->addUniqueIndex(['token_hash'], 'uniq_member_email_consent_token_hash');
        $token->addIndex(['expires_at'], 'idx_member_email_consent_token_expires');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('member_email_consent_token');
        $schema->getTable('member')->dropColumn('email_consent');
    }
}
