<?php

namespace App\Tests\Integration\Data\Membership\Member\Processor;

use App\Data\Membership\Member\Processor\DoctrineMemberProcessor;
use App\Data\Membership\Member\Provider\DoctrineMemberProvider;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
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
            $first->withContribution(ContributionCategory::IndividualSenior, 5000, null),
            $second->withContribution(ContributionCategory::IndividualSenior, 6000, null),
        ]);
        $this->entityManager->clear();

        $provider = $this->service(DoctrineMemberProvider::class);
        self::assertSame(5000, $provider->find($first->id)?->contributionAmountCents);
        self::assertSame(6000, $provider->find($second->id)?->contributionAmountCents);
    }

    public function testSaveAllWritesNothingWhenOneMemberWasChangedInTheMeantime(): void
    {
        $processor = $this->service(DoctrineMemberProcessor::class);
        $first = $processor->save($this->member('11111111-1111-4111-8111-111111111111', 'M-0001'));
        $staleSecond = $processor->save($this->member('22222222-2222-4222-8222-222222222222', 'M-0002'));
        $processor->save($staleSecond->withContribution(ContributionCategory::IndividualSenior, 1000, null));

        try {
            $processor->saveAll([
                $first->withContribution(ContributionCategory::IndividualSenior, 5000, null),
                $staleSecond->withContribution(ContributionCategory::IndividualSenior, 6000, null),
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
