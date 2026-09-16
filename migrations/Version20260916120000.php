<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passwortloses Redaktions-Login: adds admin_login_token (per Mail verschickte, '
            .'einmalige Anmeldelinks), admin_access_token and admin_refresh_token (Sitzungs-Tokens, '
            .'siehe SessionTokenIssuer), drops cms_user.password_hash.';
    }

    public function up(Schema $schema): void
    {
        $loginToken = $schema->createTable('admin_login_token');
        $loginToken->addColumn('id', 'string', ['length' => 36]);
        $loginToken->addColumn('email', 'string', ['length' => 180]);
        $loginToken->addColumn('token_hash', 'string', ['length' => 64]);
        $loginToken->addColumn('expires_at', 'datetime_immutable');
        $loginToken->addColumn('consumed_at', 'datetime_immutable', ['notnull' => false]);
        $loginToken->setPrimaryKey(['id']);
        $loginToken->addUniqueIndex(['token_hash'], 'uniq_admin_login_token_hash');
        $loginToken->addIndex(['expires_at'], 'idx_admin_login_token_expires');

        $accessToken = $schema->createTable('admin_access_token');
        $accessToken->addColumn('id', 'string', ['length' => 36]);
        $accessToken->addColumn('user_id', 'string', ['length' => 36]);
        $accessToken->addColumn('token_hash', 'string', ['length' => 64]);
        $accessToken->addColumn('expires_at', 'datetime_immutable');
        $accessToken->addColumn('created_at', 'datetime_immutable');
        $accessToken->setPrimaryKey(['id']);
        $accessToken->addUniqueIndex(['token_hash'], 'uniq_admin_access_token_hash');
        $accessToken->addIndex(['expires_at'], 'idx_admin_access_token_expires');

        $refreshToken = $schema->createTable('admin_refresh_token');
        $refreshToken->addColumn('id', 'string', ['length' => 36]);
        $refreshToken->addColumn('user_id', 'string', ['length' => 36]);
        $refreshToken->addColumn('token_hash', 'string', ['length' => 64]);
        $refreshToken->addColumn('expires_at', 'datetime_immutable');
        $refreshToken->addColumn('absolute_expires_at', 'datetime_immutable');
        $refreshToken->addColumn('created_at', 'datetime_immutable');
        $refreshToken->addColumn('revoked_at', 'datetime_immutable', ['notnull' => false]);
        $refreshToken->setPrimaryKey(['id']);
        $refreshToken->addUniqueIndex(['token_hash'], 'uniq_admin_refresh_token_hash');
        $refreshToken->addIndex(['user_id'], 'idx_admin_refresh_token_user');
        $refreshToken->addIndex(['expires_at'], 'idx_admin_refresh_token_expires');

        $schema->getTable('cms_user')->dropColumn('password_hash');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('cms_user')->addColumn('password_hash', 'string', ['length' => 255, 'default' => '']);

        $schema->dropTable('admin_refresh_token');
        $schema->dropTable('admin_access_token');
        $schema->dropTable('admin_login_token');
    }
}
