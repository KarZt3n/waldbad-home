<?php

namespace App\Tests\Integration\Data\Membership\Member\Processor;

use App\Data\Membership\Member\Processor\DoctrineMemberImportTransaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;

final class DoctrineMemberImportTransactionTest extends TestCase
{
    public function testFailureRollsBackAllWrites(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($connection, $configuration);
        $connection->executeStatement('CREATE TABLE import_test (id INTEGER PRIMARY KEY)');
        try {
            (new DoctrineMemberImportTransaction($entityManager))->execute(static function () use ($connection): void {
                $connection->executeStatement('INSERT INTO import_test VALUES (1)');
                throw new \RuntimeException('Synthetic failure');
            });
            self::fail('The exception must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic failure', $exception->getMessage());
        }
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM import_test'));
    }
}
