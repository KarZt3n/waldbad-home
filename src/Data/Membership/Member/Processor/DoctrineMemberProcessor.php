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
        if ($member->version === 0) {
            return $this->create($member);
        }

        $entity = $this->entityManager->find(MemberEntity::class, $member->id);
        if ($entity === null) {
            throw new MemberNotFoundException($member->id);
        }
        try {
            $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $member->version);
            $this->mapper->updateEntity($member, $entity, $this->clock->now());
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConcurrencyException(
                'Das Mitglied wurde zwischenzeitlich geändert. Bitte lade die Daten neu.',
                previous: $exception,
            );
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->duplicateMemberNumberException($member, $exception);
        }

        return $this->mapper->toModel($entity);
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

    private function create(Member $member): Member
    {
        $entity = $this->mapper->createEntity($member, $this->clock->now());
        try {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->duplicateMemberNumberException($member, $exception);
        }

        return $this->mapper->toModel($entity);
    }

    private function duplicateMemberNumberException(Member $member, UniqueConstraintViolationException $exception): BusinessRuleViolationException
    {
        return new BusinessRuleViolationException(
            sprintf('Die Mitgliedsnummer "%s" ist bereits vergeben.', $member->memberNumber),
            previous: $exception,
        );
    }
}
