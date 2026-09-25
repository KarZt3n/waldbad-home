<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fotos: adds photo_album (Einträge im Fotoarchiv mit Titel, Datum und Sichtbarkeit) and '
            .'photo_album_action (Aktionsbuttons je Eintrag, z. B. „Öffnen“, „Ergebnisse“).';
    }

    public function up(Schema $schema): void
    {
        $album = $schema->createTable('photo_album');
        $album->addColumn('id', 'string', ['length' => 36]);
        $album->addColumn('title', 'string', ['length' => 250]);
        $album->addColumn('album_date', 'date_immutable');
        $album->addColumn('visible', 'boolean');
        $album->addColumn('created_at', 'datetime_immutable');
        $album->addColumn('updated_at', 'datetime_immutable');
        $album->setPrimaryKey(['id']);
        $album->addIndex(['visible', 'album_date'], 'idx_photo_album_visible_date');

        $action = $schema->createTable('photo_album_action');
        $action->addColumn('id', 'string', ['length' => 36]);
        $action->addColumn('album_id', 'string', ['length' => 36]);
        $action->addColumn('position', 'integer');
        $action->addColumn('label', 'string', ['length' => 80]);
        $action->addColumn('url', 'string', ['length' => 2048, 'notnull' => false]);
        $action->addColumn('page_id', 'string', ['length' => 36, 'notnull' => false]);
        $action->setPrimaryKey(['id']);
        $action->addIndex(['album_id', 'position'], 'idx_photo_album_action_album_position');
        $action->addForeignKeyConstraint('photo_album', ['album_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('photo_album_action');
        $schema->dropTable('photo_album');
    }
}
