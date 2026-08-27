<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores media metadata and image sources in the database.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('media_image');
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('url', 'string', ['length' => 255]);
        $table->addColumn('original_name', 'string', ['length' => 255]);
        $table->addColumn('mime_type', 'string', ['length' => 80]);
        $table->addColumn('size', 'integer');
        $table->addColumn('width', 'integer');
        $table->addColumn('height', 'integer');
        $table->addColumn('source', 'string', ['length' => 300, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['url'], 'uniq_media_image_url');
        $table->addIndex(['created_at'], 'idx_media_image_created');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('media_image');
    }
}
