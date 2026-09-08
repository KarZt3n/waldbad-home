<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\ImportMembersRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;
use App\Logic\Membership\Member\UseCase\ImportMembersUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ImportMembersUseCaseTest extends TestCase
{
    /**
     * Ein Re-Import einer zuvor über die Oberfläche exportierten Datei referenziert das zahlende
     * Mitglied nur über die Mitgliedsnummer (payerMemberNumber) — die exportierte interne ID
     * (payerMemberId) ist nach einer Neubefüllung der Datenbank ungültig. Steht die zahlende Zeile
     * erst weiter hinten in der Datei, darf der Import trotzdem nicht scheitern.
     */
    public function testResolvesPayerAppearingLaterInTheFileWithoutError(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);

        $createdOrder = [];
        $orchestrator = $this->createMock(MemberOnboardingOrchestrator::class);
        $orchestrator->expects(self::exactly(2))
            ->method('createFromRequest')
            ->willReturnCallback(function (CreateMemberRequest $request) use (&$createdOrder): Member {
                $createdOrder[] = $request->memberNumber;

                return $this->member($request->memberNumber);
            });

        $useCase = new ImportMembersUseCase($members, $orchestrator, new MemberModelFactory());
        $result = $useCase->execute(new ImportMembersRequest([
            $this->row('CHILD-1', payerMemberNumber: 'PAYER-1'),
            $this->row('PAYER-1'),
        ]));

        self::assertSame([], $result->errors);
        self::assertSame(2, $result->created);
        self::assertSame(['PAYER-1', 'CHILD-1'], $createdOrder);
    }

    /**
     * "GHOST-1" ist weder in der Importdatei noch in der Datenbank bekannt — die topologische
     * Sortierung kann eine solche Abhängigkeit nicht auflösen (sie kennt nur Zeilen aus der
     * gleichen Datei), daher landet die Zeile unverändert beim Orchestrator, der sie regulär
     * anhand der Geschäftsregel ablehnt.
     */
    public function testUnresolvablePayerNumberIsReportedAsError(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);
        $orchestrator = $this->createMock(MemberOnboardingOrchestrator::class);
        $orchestrator->method('createFromRequest')->willThrowException(
            new BusinessRuleViolationException('Es wurde kein zahlendes Mitglied mit der Mitgliedsnummer "GHOST-1" gefunden.'),
        );

        $useCase = new ImportMembersUseCase($members, $orchestrator, new MemberModelFactory());
        $result = $useCase->execute(new ImportMembersRequest([
            $this->row('CHILD-1', payerMemberNumber: 'GHOST-1'),
        ]));

        self::assertSame(0, $result->created);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('GHOST-1', $result->errors[0]->message);
    }

    /**
     * Ein Familienmitglied kann in der Importdatei vor seinem Hauptmitglied stehen — insbesondere
     * wenn das Hauptmitglied selbst noch auf einen Zahler wartet und deshalb erst später in der
     * (nach payerMemberNumber sortierten) Verarbeitungsreihenfolge an die Reihe kommt. Das darf
     * nicht an der sonst strengen "Hauptmitglied muss existieren"-Prüfung scheitern.
     */
    public function testFamilyMemberDoesNotFailWhenPrimaryIsCreatedLaterInTheSameBatch(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);

        $orchestrator = $this->createMock(MemberOnboardingOrchestrator::class);
        $orchestrator->expects(self::exactly(2))
            ->method('createFromRequest')
            ->willReturnCallback(function (CreateMemberRequest $request): Member {
                return $this->member($request->memberNumber);
            });

        $childRow = new CreateMemberRequest(
            memberNumber: 'CHILD-1',
            primaryMemberNumber: 'HEAD-1',
            salutation: Salutation::Diverse,
            lastName: 'Test',
            firstName: 'Kind',
            birthDate: new \DateTimeImmutable('2010-01-01'),
            street: 'Testweg 1',
            postalCode: '12345',
            city: 'Testort',
            email: null,
            phone: null,
            familyRole: FamilyRole::Child,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::OtherMember,
            payerMemberId: null,
            payerMemberNumber: 'HEAD-1',
            nextBookingMonth: 3,
            nextBookingYear: 2027,
        );

        $useCase = new ImportMembersUseCase($members, $orchestrator, new MemberModelFactory());
        $result = $useCase->execute(new ImportMembersRequest([
            $childRow,
            $this->row('HEAD-1'),
        ]));

        self::assertSame([], $result->errors);
        self::assertSame(2, $result->created);
    }

    /**
     * Beim Aktualisieren eines bereits bestehenden Mitglieds muss payerMemberNumber ebenso zur
     * internen ID aufgelöst werden wie beim Neuanlegen — sonst würde eine stale payerMemberId aus
     * einer alten Export-Datei unverändert übernommen.
     */
    public function testUpdateResolvesPayerMemberNumberToCurrentId(): void
    {
        $existing = $this->member('CHILD-1');
        $payer = $this->member('PAYER-1');

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturnMap([
            ['CHILD-1', $existing],
            ['PAYER-1', $payer],
        ]);
        $members->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (Member $member) use ($payer): Member {
                self::assertSame($payer->id, $member->payerMemberId);

                return $member;
            });

        $orchestrator = $this->createMock(MemberOnboardingOrchestrator::class);
        $orchestrator->expects(self::never())->method('createFromRequest');

        $useCase = new ImportMembersUseCase($members, $orchestrator, new MemberModelFactory());
        $result = $useCase->execute(new ImportMembersRequest([
            $this->row('CHILD-1', payerMemberNumber: 'PAYER-1'),
        ]));

        self::assertSame([], $result->errors);
        self::assertSame(1, $result->updated);
    }

    private function row(string $number, ?string $payerMemberNumber = null): CreateMemberRequest
    {
        return new CreateMemberRequest(
            memberNumber: $number,
            primaryMemberNumber: null,
            salutation: Salutation::Diverse,
            lastName: 'Test',
            firstName: 'Beispiel',
            birthDate: new \DateTimeImmutable('2000-01-01'),
            street: 'Testweg 1',
            postalCode: '12345',
            city: 'Testort',
            email: null,
            phone: null,
            familyRole: $payerMemberNumber === null ? FamilyRole::None : FamilyRole::Child,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: 'Test',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: 'TEST-MANDATE',
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $payerMemberNumber === null ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: null,
            payerMemberNumber: $payerMemberNumber,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
        );
    }

    private function member(string $number): Member
    {
        return new Member(
            id: 'id-' . $number,
            memberNumber: $number,
            primaryMemberNumber: $number,
            salutation: Salutation::Diverse,
            lastName: 'Test',
            firstName: 'Beispiel',
            birthDate: new \DateTimeImmutable('2000-01-01'),
            street: 'Testweg 1',
            postalCode: '12345',
            city: 'Testort',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: 'Test',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $number,
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
            version: 1,
        );
    }
}
