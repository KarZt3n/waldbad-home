<?php

namespace App\Tests\Integration\Data\Membership\Member\Provider;

use App\Data\Membership\Member\Entity\MemberContributionChargeEntity;
use App\Data\Membership\Member\Entity\MemberEntity;
use App\Data\Membership\Member\Entity\MemberRemarkEntity;
use App\Data\Membership\Member\Mapper\MemberMapper;
use App\Data\Membership\Member\Processor\DoctrineMemberProcessor;
use App\Data\Membership\Member\Provider\DoctrineMemberProvider;
use App\Data\Common\SystemClock;
use App\Logic\Membership\Member\Model\{ContributionCharge, FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Remark, Salutation};
use App\Logic\Membership\PaymentInterval;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class DoctrineMemberProviderTest extends TestCase
{
    private EntityManager $entityManager;
    private int $queryCount = 0;

    protected function setUp(): void
    {
        $test = $this;
        $logger = new class($test) extends AbstractLogger {
            public function __construct(private readonly DoctrineMemberProviderTest $test)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (str_starts_with((string) $message, 'Executing')) {
                    $this->test->countQuery();
                }
            }
        };
        $configuration = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 6).'/src/Data/Membership/Member/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $configuration->setMiddlewares([new Middleware($logger)]);
        $configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->entityManager = new EntityManager($connection, $configuration);
        (new SchemaTool($this->entityManager))->createSchema(array_map(
            $this->entityManager->getClassMetadata(...),
            [MemberEntity::class, MemberRemarkEntity::class, MemberContributionChargeEntity::class],
        ));
    }

    public function countQuery(): void
    {
        ++$this->queryCount;
    }

    public function testSearchLoadsRemarksAndChargesWithAConstantNumberOfQueries(): void
    {
        $processor = new DoctrineMemberProcessor($this->entityManager, new MemberMapper(), new SystemClock());
        for ($index = 1; $index <= 20; ++$index) {
            $processor->save($this->member($index));
        }
        $this->entityManager->clear();
        $this->queryCount = 0;

        $members = (new DoctrineMemberProvider($this->entityManager, new MemberMapper()))->search(null);

        self::assertCount(20, $members);
        self::assertSame('Bemerkung 7', $members[6]->remarks[0]->text);
        self::assertSame(1200, $members[6]->oneTimeCharges[0]->amountCents);
        self::assertLessThanOrEqual(3, $this->queryCount, sprintf('%d Abfragen', $this->queryCount));
    }

    private function member(int $index): Member
    {
        $memberNumber = sprintf('M-%04d', $index);

        return new Member(
            id: sprintf('00000000-0000-4000-8000-%012d', $index),
            memberNumber: $memberNumber,
            primaryMemberNumber: $memberNumber,
            salutation: Salutation::Diverse,
            lastName: sprintf('Muster %02d', $index),
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
            remarks: [new Remark(sprintf('10000000-0000-4000-8000-%012d', $index), 'Bemerkung '.$index, null, new \DateTimeImmutable('2026-01-01'))],
            oneTimeCharges: [new ContributionCharge(sprintf('20000000-0000-4000-8000-%012d', $index), 'Aufnahme', 1200, new \DateTimeImmutable('2026-01-01'))],
            version: 0,
            contributionLiable: true,
        );
    }
}
