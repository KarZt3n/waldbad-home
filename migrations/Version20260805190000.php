<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates CMS pages, users, guestbook entries, and contact requests.';
    }

    public function up(Schema $schema): void
    {
        $page = $schema->createTable('cms_page');
        $page->addColumn('id', 'string', ['length' => 36]);
        $page->addColumn('title', 'string', ['length' => 180]);
        $page->addColumn('slug', 'string', ['length' => 180]);
        $page->addColumn('navigation_label', 'string', ['length' => 180]);
        $page->addColumn('blocks', 'json');
        $page->addColumn('status', 'string', ['length' => 20]);
        $page->addColumn('show_in_navigation', 'boolean');
        $page->addColumn('navigation_position', 'integer');
        $page->addColumn('seo_title', 'string', ['length' => 180, 'notnull' => false]);
        $page->addColumn('seo_description', 'text', ['notnull' => false]);
        $page->addColumn('version', 'integer', ['default' => 1]);
        $page->addColumn('created_at', 'datetime_immutable');
        $page->addColumn('updated_at', 'datetime_immutable');
        $page->addColumn('published_at', 'datetime_immutable', ['notnull' => false]);
        $page->setPrimaryKey(['id']);
        $page->addUniqueIndex(['slug'], 'uniq_cms_page_slug');
        $page->addIndex(['status', 'published_at'], 'idx_cms_page_publication');

        $user = $schema->createTable('cms_user');
        $user->addColumn('id', 'string', ['length' => 36]);
        $user->addColumn('email', 'string', ['length' => 180]);
        $user->addColumn('display_name', 'string', ['length' => 180]);
        $user->addColumn('password_hash', 'string', ['length' => 255]);
        $user->addColumn('roles', 'json');
        $user->addColumn('active', 'boolean');
        $user->addColumn('version', 'integer', ['default' => 1]);
        $user->addColumn('created_at', 'datetime_immutable');
        $user->addColumn('updated_at', 'datetime_immutable');
        $user->addColumn('last_login_at', 'datetime_immutable', ['notnull' => false]);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['email'], 'uniq_cms_user_email');

        $guestbook = $schema->createTable('guestbook_entry');
        $guestbook->addColumn('id', 'string', ['length' => 36]);
        $guestbook->addColumn('display_name', 'string', ['length' => 120]);
        $guestbook->addColumn('email', 'string', ['length' => 180, 'notnull' => false]);
        $guestbook->addColumn('message', 'text');
        $guestbook->addColumn('status', 'string', ['length' => 20]);
        $guestbook->addColumn('submitted_at', 'datetime_immutable');
        $guestbook->addColumn('moderated_at', 'datetime_immutable', ['notnull' => false]);
        $guestbook->addColumn('moderated_by', 'string', ['length' => 36, 'notnull' => false]);
        $guestbook->setPrimaryKey(['id']);
        $guestbook->addIndex(['status', 'submitted_at'], 'idx_guestbook_status_submitted');

        $contact = $schema->createTable('contact_request');
        $contact->addColumn('id', 'string', ['length' => 36]);
        $contact->addColumn('name', 'string', ['length' => 120]);
        $contact->addColumn('email', 'string', ['length' => 180]);
        $contact->addColumn('subject', 'string', ['length' => 180, 'notnull' => false]);
        $contact->addColumn('message', 'text');
        $contact->addColumn('status', 'string', ['length' => 20]);
        $contact->addColumn('submitted_at', 'datetime_immutable');
        $contact->addColumn('updated_at', 'datetime_immutable');
        $contact->setPrimaryKey(['id']);
        $contact->addIndex(['status', 'submitted_at'], 'idx_contact_status_submitted');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('contact_request');
        $schema->dropTable('guestbook_entry');
        $schema->dropTable('cms_user');
        $schema->dropTable('cms_page');
    }
}
