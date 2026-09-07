<?php

namespace App\Data\Membership\Member\Provider;

use App\Logic\Membership\Member\MemberNumberGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vergibt fortlaufende Mitgliedsnummern im Format „Bad-01234“ (Format der bisherigen, externen
 * Mitgliederverwaltung — wird bei der Übernahme des Altbestands fortgeführt) über einen
 * einzeiligen Zähler (`member_number_sequence`). Der `UPDATE ... RETURNING`-lose Ansatz (UPDATE,
 * dann SELECT in derselben Transaktion) funktioniert plattformunabhängig und ist für das erwartete
 * Zugriffsaufkommen einer Vereinsverwaltung ausreichend.
 */
readonly class DoctrineMemberNumberGenerator implements MemberNumberGeneratorInterface
{
    private const string PREFIX = 'Bad-';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function next(): string
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $connection->executeStatement('UPDATE member_number_sequence SET next_value = next_value + 1 WHERE id = 1');
            $fetched = $connection->fetchOne('SELECT next_value - 1 FROM member_number_sequence WHERE id = 1');
            $value = is_numeric($fetched) ? (int) $fetched : 0;
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return sprintf('%s%05d', self::PREFIX, $value);
    }
}
