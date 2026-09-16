<?php

namespace App\Data\Event\Template\Processor;

use App\Data\Event\Template\Entity\EventTemplateActivityEntity;
use App\Data\Event\Template\Entity\EventTemplateEntity;
use App\Data\Event\Template\Mapper\EventTemplateMapper;
use App\Logic\Event\Template\EventTemplateProcessorInterface;
use App\Logic\Event\Template\Exception\EventTemplateNotFoundException;
use App\Logic\Event\Template\Model\EventTemplate;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventTemplateProcessor implements EventTemplateProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventTemplateMapper $mapper,
    ) {
    }

    public function save(EventTemplate $template): EventTemplate
    {
        $entity = $this->entityManager->find(EventTemplateEntity::class, $template->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($template);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($template, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(EventTemplateEntity::class, $id);
        if ($entity === null) {
            throw new EventTemplateNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function removeActivityReferences(string $activityId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(EventTemplateActivityEntity::class, 'activity')
            ->where('activity.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->execute();
    }
}
