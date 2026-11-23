<?php

namespace App\Tests\Integration\Data\Membership\Member\Processor;

use App\Data\Membership\Member\Entity\MemberEntity;
use App\Data\Membership\Member\Processor\DoctrineMemberProcessor;
use App\Data\Membership\Member\Provider\DoctrineMemberProvider;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\PaymentInterval;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineMemberProcessorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Der EntityManager ist im Testcontainer nicht verfügbar.');
        }
        $this->entityManager = $entityManager;
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testSaveAllPersistsEveryMember(): void
    {
        $processor = $this->service(DoctrineMemberProcessor::class);
        $first = $processor->save($this->member('11111111-1111-4111-8111-111111111111', 'M-0001'));
        $second = $processor->save($this->member('22222222-2222-4222-8222-222222222222', 'M-0002'));

        $processor->saveAll([
            $first->withContribution(ContributionCategory::IndividualSenior, 5000, null, 2026),
            $second->withContribution(ContributionCategory::IndividualSenior, 6000, null, 2026),
        ]);
        $this->entityManager->clear();

        $provider = $this->service(DoctrineMemberProvider::class);
        self::assertSame(5000, $provider->find($first->id)?->contributionAmountCents);
        self::assertSame(6000, $provider->find($second->id)?->contributionAmountCents);
        self::assertSame(2026, $provider->find($second->id)->contributionYear);
    }

    public function testDeleteOnlyMarksTheMemberAsDeletedAndHidesItFromEveryLookup(): void
    {
        $processor = $this->service(DoctrineMemberProcessor::class);
        $member = $processor->save($this->member('11111111-1111-4111-8111-111111111111', 'M-0001'));
        $other = $processor->save($this->member('22222222-2222-4222-8222-222222222222', 'M-0002'));

        $processor->delete($member->id);
        $this->entityManager->clear();

        $entity = $this->entityManager->find(MemberEntity::class, $member->id);
        self::assertNotNull($entity, 'Der Datensatz bleibt in der Datenbank erhalten.');
        self::assertNotNull($entity->getDeletedAt());

        $provider = $this->service(DoctrineMemberProvider::class);
        self::assertNull($provider->find($member->id));
        self::assertNull($provider->findByMemberNumber('M-0001'));
        self::assertSame([], $provider->findByPrimaryMemberNumber('M-0001'));
        self::assertSame([$other->id], array_map(static fn (Member $found): string => $found->id, $provider->search(null)));
        self::assertSame([$other->id], array_map(static fn (Member $found): string => $found->id, $provider->search('M-000')));

        $this->expectException(MemberNotFoundException::class);
        $processor->save($member->withContribution(ContributionCategory::IndividualSenior, 5000, null, 2026));
    }

    public function testDeletingAnAlreadyDeletedMemberFails(): void
    {
        $processor = $this->service(DoctrineMemberProcessor::class);
        $member = $processor->save($this->member('11111111-1111-4111-8111-111111111111', 'M-0001'));
        $processor->delete($member->id);

        $this->expectException(MemberNotFoundException::class);
        $processor->delete($member->id);
    }

    public function testDeleteAllMarksEveryMemberOrNoneWhenOneIsMissing(): void
    {
        $processor = $this->service(DoctrineMemberProcessor::class);
        $first = $processor->save($this->member('11111111-1111-4111-8111-111111111111', 'M-0001'));
        $second = $processor->save($this->member('22222222-2222-4222-8222-222222222222', 'M-0002'));
        $third = $processor->save($this->member('33333333-3333-4333-8333-333333333333', 'M-0003'));

        try {
            $processor->deleteAll([$first->id, '44444444-4444-4444-8444-444444444444']);
            self::fail('Ein unbekanntes Mitglied muss den Sammel-Löschvorgang abbrechen.');
        } catch (MemberNotFoundException) {
        }
        $this->entityManager->clear();
        $provider = $this->service(DoctrineMemberProvider::class);
        self::assertNotNull($provider->find($first->id));

        $processor->deleteAll([$first->id, $second->id]);
        $this->entityManager->clear();
        self::assertNull($provider->find($first->id));
        self::assertNull($provider->find($second->id));
        self::assertNotNull($provider->find($third->id));
        self::assertNotNull($this->entityManager->find(MemberEntity::class, $second->id)?->getDeletedAt());
    }

    public function testSaveAllWritesNothingWhenOneMemberWasChangedInTheMeantime(): void
    {
        $processor = $this->service(DoctrineMemberProcessor::class);
        $first = $processor->save($this->member('11111111-1111-4111-8111-111111111111', 'M-0001'));
        $staleSecond = $processor->save($this->member('22222222-2222-4222-8222-222222222222', 'M-0002'));
        $processor->save($staleSecond->withContribution(ContributionCategory::IndividualSenior, 1000, null, 2026));

        try {
            $processor->saveAll([
                $first->withContribution(ContributionCategory::IndividualSenior, 5000, null, 2026),
                $staleSecond->withContribution(ContributionCategory::IndividualSenior, 6000, null, 2026),
            ]);
            self::fail('Ein zwischenzeitlich geändertes Mitglied muss den Sammel-Speichervorgang abbrechen.');
        } catch (ConcurrencyException) {
        }
        $this->entityManager->clear();

        $provider = $this->service(DoctrineMemberProvider::class);
        self::assertNull($provider->find($first->id)?->contributionAmountCents);
        self::assertSame(1000, $provider->find($staleSecond->id)?->contributionAmountCents);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        if (!$service instanceof $class) {
            throw new \LogicException(sprintf('Der Dienst %s ist im Testcontainer nicht verfügbar.', $class));
        }

        return $service;
    }

    private function member(string $id, string $memberNumber): Member
    {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $memberNumber,
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $memberNumber,
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
            contributionLiable: true,
        );
    }
}
