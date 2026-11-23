<?php

namespace App\Data\Membership\Member\Provider;

use App\Data\Membership\Member\Entity\MemberEntity;
use App\Data\Membership\Member\Mapper\MemberMapper;
use App\Logic\Membership\Member\MemberProviderInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberListEntry;
use App\Logic\Membership\Member\Model\MemberSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Liefert ausschließlich nicht gelöschte Mitglieder — als gelöscht markierte Datensätze (Soft-Delete,
 * siehe `DoctrineMemberProcessor::delete()`) verhalten sich für die übrige Anwendung so, als gäbe es
 * sie nicht mehr. Einzige Ausnahme ist `findAllSnapshots()` für den vollständigen Export.
 */
readonly class DoctrineMemberProvider implements MemberProviderInterface
{
    private const array SEARCH_FIELDS = [
        'memberNumber', 'primaryMemberNumber', 'lastName', 'firstName', 'street', 'postalCode', 'city', 'email', 'phone',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberMapper $mapper,
    ) {
    }

    public function find(string $id): ?Member
    {
        $entity = $this->entityManager->find(MemberEntity::class, $id);

        return $entity === null || $entity->isDeleted() ? null : $this->mapper->toModel($entity);
    }

    public function findByMemberNumber(string $memberNumber): ?Member
    {
        $entity = $this->entityManager->getRepository(MemberEntity::class)->findOneBy(['memberNumber' => $memberNumber, 'deletedAt' => null]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findByEmail(string $email): array
    {
        // Case-insensitiv unabhängig von der DB-Kollation, statt sich darauf zu verlassen.
        $result = $this->entityManager->getRepository(MemberEntity::class)->createQueryBuilder('m')
            ->where('LOWER(m.email) = LOWER(:email)')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getResult();

        $entities = [];
        foreach (is_array($result) ? $result : [] as $item) {
            if ($item instanceof MemberEntity) {
                $entities[] = $item;
            }
        }

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function findByPrimaryMemberNumber(string $primaryMemberNumber): array
    {
        $entities = $this->entityManager->getRepository(MemberEntity::class)->findBy(['primaryMemberNumber' => $primaryMemberNumber, 'deletedAt' => null]);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function findByPayerMemberId(string $payerMemberId): array
    {
        $entities = $this->entityManager->getRepository(MemberEntity::class)->findBy(['payerMemberId' => $payerMemberId, 'deletedAt' => null]);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function search(?string $term): array
    {
        return $this->findMatching((string) $term, self::SEARCH_FIELDS);
    }

    public function searchByName(string $term): array
    {
        return $this->findMatching($term, ['lastName', 'firstName']);
    }

    public function searchListEntries(?string $term): array
    {
        $rows = $this->matchingQuery((string) $term, self::SEARCH_FIELDS)
            ->select('m.id', 'm.memberNumber', 'm.primaryMemberNumber', 'm.lastName', 'm.firstName', 'm.birthDate', 'm.street', 'm.city', 'm.leftAt')
            ->getQuery()
            ->getArrayResult();

        $entries = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entries[] = new MemberListEntry(
                id: self::stringValue($row['id']),
                memberNumber: self::stringValue($row['memberNumber']),
                primaryMemberNumber: self::stringValue($row['primaryMemberNumber']),
                lastName: self::stringValue($row['lastName']),
                firstName: self::stringValue($row['firstName']),
                birthDate: self::dateValue($row['birthDate']) ?? throw new \UnexpectedValueException('Mitglied ohne Geburtsdatum.'),
                street: self::stringValue($row['street']),
                city: self::stringValue($row['city']),
                leftAt: self::dateValue($row['leftAt']),
            );
        }

        return $entries;
    }

    /**
     * @param non-empty-list<string> $fields
     *
     * @return list<Member>
     */
    private function findMatching(string $term, array $fields): array
    {
        $result = $this->matchingQuery($term, $fields)->getQuery()->getResult();
        $entities = [];
        foreach (is_array($result) ? $result : [] as $item) {
            if ($item instanceof MemberEntity) {
                $entities[] = $item;
            }
        }
        $this->preloadCollections($entities);

        return array_map($this->mapper->toModel(...), $entities);
    }

    /**
     * @param non-empty-list<string> $fields
     */
    private function matchingQuery(string $term, array $fields): QueryBuilder
    {
        // "member" ist ein reserviertes DQL-Schlüsselwort (MEMBER OF) und darf nicht als Alias
        // verwendet werden.
        $queryBuilder = $this->entityManager->getRepository(MemberEntity::class)->createQueryBuilder('m')
            ->where('m.deletedAt IS NULL')
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC');

        // Echte Volltextsuche: Der Suchbegriff wird an Leerzeichen in einzelne Wörter zerlegt, und
        // jedes Wort muss (unabhängig von Feld und Reihenfolge) irgendwo unter den durchsuchten
        // Feldern vorkommen — "Muster Max" findet damit z. B. auch einen Datensatz mit
        // firstName=Max/lastName=Muster, nicht nur eine wortwörtliche Übereinstimmung in einem
        // einzelnen Feld.
        $term = trim($term);
        if ($term !== '') {
            $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($words as $index => $word) {
                $parameter = 'term'.$index;
                $queryBuilder->andWhere(implode(' OR ', array_map(
                    static fn (string $field): string => 'm.'.$field.' LIKE :'.$parameter,
                    $fields,
                )))->setParameter($parameter, '%'.$word.'%');
            }
        }

        return $queryBuilder;
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : throw new \UnexpectedValueException('Unerwarteter Wert in der Mitgliederliste.');
    }

    private static function dateValue(mixed $value): ?\DateTimeImmutable
    {
        return match (true) {
            $value === null => null,
            $value instanceof \DateTimeImmutable => $value,
            $value instanceof \DateTimeInterface => \DateTimeImmutable::createFromInterface($value),
            default => throw new \UnexpectedValueException('Unerwartetes Datum in der Mitgliederliste.'),
        };
    }

    /**
     * Lädt Bemerkungen und Einmalgebühren für alle Treffer in je einer Abfrage pro Block, statt sie
     * beim Mappen einzeln pro Mitglied nachzuladen (2 Abfragen je Mitglied). Die Blockgröße bleibt
     * unter der Parametergrenze von SQL Server (2100).
     *
     * @param list<MemberEntity> $entities
     */
    private function preloadCollections(array $entities): void
    {
        foreach (array_chunk(array_map(static fn (MemberEntity $entity): string => $entity->getId(), $entities), 1000) as $ids) {
            foreach (['remarks' => 'createdAt', 'oneTimeCharges' => 'chargedAt'] as $association => $orderField) {
                $this->entityManager->createQueryBuilder()
                    ->select('m', 'c')
                    ->from(MemberEntity::class, 'm')
                    ->leftJoin('m.'.$association, 'c')
                    ->where('m.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->orderBy('c.'.$orderField, 'ASC')
                    ->getQuery()
                    ->getResult();
            }
        }
    }

    public function findAllSnapshots(): array
    {
        $entities = $this->entityManager->getRepository(MemberEntity::class)->findBy([], ['memberNumber' => 'ASC']);

        return array_map(
            fn (MemberEntity $entity): MemberSnapshot => new MemberSnapshot(
                $this->mapper->toModel($entity),
                $entity->getCreatedAt(),
                $entity->getUpdatedAt(),
                $entity->getDeletedAt(),
            ),
            $entities,
        );
    }
}
