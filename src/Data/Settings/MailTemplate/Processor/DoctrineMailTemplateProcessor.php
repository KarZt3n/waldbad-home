<?php

namespace App\Data\Settings\MailTemplate\Processor;

use App\Data\Settings\MailTemplate\Entity\MailTemplateEntity;
use App\Data\Settings\MailTemplate\Mapper\MailTemplateMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Settings\MailTemplate\MailTemplateProcessorInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMailTemplateProcessor implements MailTemplateProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailTemplateMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(MailTemplate $template): MailTemplate
    {
        $entity = $this->entityManager->find(MailTemplateEntity::class, $template->key->value);
        $now = $this->clock->now();
        if ($entity === null) {
            $entity = $this->mapper->createEntity($template, $now);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($template, $entity, $now);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
