<?php

namespace App\Data\Membership\Member\Processor;

use App\Data\Membership\Member\Entity\MemberEntity;
use App\Data\Membership\Member\Mapper\MemberMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\MemberProcessorInterface;
use App\Logic\Membership\Member\Model\Member;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

readonly class DoctrineMemberProcessor implements MemberProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(Member $member): Member
    {
        $entity = $this->stage($member);
        $this->flush(sprintf('Die Mitgliedsnummer "%s" ist bereits vergeben.', $member->memberNumber));

        return $this->mapper->toModel($entity);
    }

    public function saveAll(array $members): void
    {
        if ($members === []) {
            return;
        }
        // Ein einziger flush: Jeder flush prüft alle verwalteten Entities auf Änderungen, ein flush
        // pro Mitglied wird bei vielen geladenen Mitgliedern quadratisch langsam.
        foreach ($members as $member) {
            $this->stage($member);
        }
        $this->flush('Eine der Mitgliedsnummern ist bereits vergeben.');
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(MemberEntity::class, $id);
        if ($entity === null) {
            throw new MemberNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    private function stage(Member $member): MemberEntity
    {
        if ($member->version === 0) {
            $entity = $this->mapper->createEntity($member, $this->clock->now());
            $this->entityManager->persist($entity);

            return $entity;
        }

        $entity = $this->entityManager->find(MemberEntity::class, $member->id);
        if ($entity === null) {
            throw new MemberNotFoundException($member->id);
        }
        try {
            $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $member->version);
        } catch (OptimisticLockException $exception) {
            throw $this->concurrencyException($exception);
        }
        $this->mapper->updateEntity($member, $entity, $this->clock->now());

        return $entity;
    }

    private function flush(string $duplicateMemberNumberMessage): void
    {
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw $this->concurrencyException($exception);
        } catch (UniqueConstraintViolationException $exception) {
            throw new BusinessRuleViolationException($duplicateMemberNumberMessage, previous: $exception);
        }
    }

    private function concurrencyException(OptimisticLockException $exception): ConcurrencyException
    {
        return new ConcurrencyException(
            'Das Mitglied wurde zwischenzeitlich geändert. Bitte lade die Daten neu.',
            previous: $exception,
        );
    }
}
