<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds admin_login_session (Sitzungshistorie der Redaktion) and links refresh tokens to their session.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_login_session (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, '
            .'started_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, '
            .'user_agent VARCHAR(255) DEFAULT NULL, ended_at DATETIME DEFAULT NULL, end_reason VARCHAR(20) DEFAULT NULL, '
            .'INDEX idx_admin_login_session_user_ended (user_id, ended_at), INDEX idx_admin_login_session_started (started_at), '
            .'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_refresh_token ADD session_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_refresh_token DROP session_id');
        $this->addSql('DROP TABLE admin_login_session');
    }
}
