<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds page_extension_texts (redaktionelle Texte der Seitenerweiterungen) and membership_application.accepted_declarations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page_extension_texts (extension_key VARCHAR(60) NOT NULL, texts JSON NOT NULL, '
            .'updated_at DATETIME NOT NULL, PRIMARY KEY (extension_key)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE membership_application ADD accepted_declarations JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership_application DROP accepted_declarations');
        $this->addSql('DROP TABLE page_extension_texts');
    }
}
