<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create folders, bookmarks and the configured Messenger transport';
    }

    public function up(Schema $schema): void
    {
        $folder = $schema->createTable('folder');
        $folder->addColumn('id', 'integer', ['autoincrement' => true]);
        $folder->addColumn('name', 'string', ['length' => 120]);
        $folder->addColumn('parent_id', 'integer', ['notnull' => false]);
        $folder->setPrimaryKey(['id']);
        $folder->addForeignKeyConstraint('folder', ['parent_id'], ['id'], ['onDelete' => 'RESTRICT']);

        $bookmark = $schema->createTable('bookmark');
        $bookmark->addColumn('id', 'integer', ['autoincrement' => true]);
        $bookmark->addColumn('title', 'string', ['length' => 255]);
        $bookmark->addColumn('url', 'string', ['length' => 2048]);
        $bookmark->addColumn('created_at', 'datetime_immutable');
        $bookmark->addColumn('folder_id', 'integer', ['notnull' => false]);
        $bookmark->setPrimaryKey(['id']);
        $bookmark->addForeignKeyConstraint('folder', ['folder_id'], ['id'], ['onDelete' => 'SET NULL']);

        // The default Symfony configuration uses Doctrine for the Messenger transport.
        $messages = $schema->createTable('messenger_messages');
        $messages->addColumn('id', 'bigint', ['autoincrement' => true]);
        $messages->addColumn('body', 'text');
        $messages->addColumn('headers', 'text');
        $messages->addColumn('queue_name', 'string', ['length' => 190]);
        $messages->addColumn('created_at', 'datetime_immutable');
        $messages->addColumn('available_at', 'datetime_immutable');
        $messages->addColumn('delivered_at', 'datetime_immutable', ['notnull' => false]);
        $messages->setPrimaryKey(['id']);
        $messages->addIndex(['queue_name', 'available_at', 'delivered_at', 'id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('messenger_messages');
        $schema->dropTable('bookmark');
        $schema->dropTable('folder');
    }
}
