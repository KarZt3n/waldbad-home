<?php

namespace App\Data\Membership\Application\Processor;

use App\Data\Membership\Application\Entity\MembershipApplicationEntity;
use App\Data\Membership\Application\Mapper\MembershipApplicationMapper;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Membership\Application\Exception\MembershipApplicationNotFoundException;
use App\Logic\Membership\Application\MembershipApplicationProcessorInterface;
use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Model\MembershipApplication;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

readonly class DoctrineMembershipApplicationProcessor implements MembershipApplicationProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembershipApplicationMapper $mapper,
    ) {
    }

    public function save(MembershipApplication $application): MembershipApplication
    {
        if ($application->version === 0) {
            $entity = $this->mapper->createEntity($application);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            return $this->mapper->toModel($entity);
        }

        $entity = $this->entityManager->find(MembershipApplicationEntity::class, $application->id);
        if ($entity === null) {
            throw new MembershipApplicationNotFoundException($application->id);
        }
        try {
            $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $application->version);
            $this->mapper->updateEntity($application, $entity);
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConcurrencyException(
                'Der Mitgliedsantrag wurde zwischenzeitlich geändert. Bitte lade die Daten neu.',
                previous: $exception,
            );
        }

        return $this->mapper->toModel($entity);
    }

    public function claimPending(int $limit, \DateTimeImmutable $at): array
    {
        $connection = $this->entityManager->getConnection();
        $entities = [];
        $connection->beginTransaction();
        try {
            $entities = $this->entityManager->getRepository(MembershipApplicationEntity::class)->findBy(
                ['status' => ApplicationStatus::Pending->value],
                ['submittedAt' => 'ASC'],
                $limit,
            );
            foreach ($entities as $entity) {
                $this->entityManager->lock($entity, LockMode::PESSIMISTIC_WRITE);
                $entity->updateStatus(ApplicationStatus::Processing->value, null, null, $at, $at, null);
            }
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return array_map($this->mapper->toModel(...), $entities);
    }
}
