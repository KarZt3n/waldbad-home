<?php

namespace App\Data\Membership\ContributionRate\Processor;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Data\Membership\ContributionRate\Mapper\ContributionRateMapper;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\ContributionRateProcessorInterface;
use App\Logic\Membership\ContributionRate\Exception\ContributionRateNotFoundException;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineContributionRateProcessor implements ContributionRateProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContributionRateMapper $mapper,
    ) {
    }

    public function save(ContributionRate $rate): ContributionRate
    {
        $entity = $this->entityManager->find(ContributionRateEntity::class, $rate->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($rate);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($rate, $entity);
        }
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new BusinessRuleViolationException(
                sprintf(
                    'Für die Kategorie "%s" existiert bereits ein Beitragssatz.',
                    $rate->category !== null ? $rate->category->value : $rate->label,
                ),
                previous: $exception,
            );
        }

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(ContributionRateEntity::class, $id);
        if ($entity === null) {
            throw new ContributionRateNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
