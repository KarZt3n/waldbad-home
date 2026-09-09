<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds action_pin_hashes to pin_settings so each protected module/functionality can '
            .'optionally have its own PIN instead of always falling back to the global one.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('pin_settings');
        $table->addColumn('action_pin_hashes', 'json', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('pin_settings');
        $table->dropColumn('action_pin_hashes');
    }
}
