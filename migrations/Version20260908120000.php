<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds contribution_liable to member so board members (Vorstand) can be marked as '
            .'exempt from membership fees.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('member');
        $table->addColumn('contribution_liable', 'boolean', ['default' => true]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('member')->dropColumn('contribution_liable');
    }
}
