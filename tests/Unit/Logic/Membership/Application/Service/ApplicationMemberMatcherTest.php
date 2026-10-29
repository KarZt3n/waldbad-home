<?php

namespace App\Tests\Unit\Logic\Membership\Application\Service;

use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;
use App\Logic\Membership\Application\Service\ApplicationMemberMatcher;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ApplicationMemberMatcherTest extends TestCase
{
    private const string NOW = '2026-06-01';

    /** Ein Tippfehler im Vornamen oder ein geänderter Nachname verhindert den Treffer nicht, solange Geburtsdatum und der andere Name passen. */
    public function testMatchesDespiteOneDifferingNameWhenTheBirthDateMatches(): void
    {
        $member = $this->member('1', 'Bad-1', 'Erika', 'Musterfrau', '1990-06-15');

        $match = $this->matcher([$member])->match($this->application([$this->applicant('Erica', 'Musterfrau', '1990-06-15')]), $this->now());

        self::assertNotNull($match);
        self::assertSame('Bad-1', $match->primaryMemberNumber);
        self::assertSame($member, $match->matchedMembers[0]);
        self::assertSame($member, $match->bankAccountMember);
    }

    /** Gleicher Name allein reicht nicht: ohne passendes Geburtsdatum ist es eine andere Person. */
    public function testDoesNotMatchANamesakeWithAnotherBirthDate(): void
    {
        $matcher = $this->matcher([$this->member('1', 'Bad-1', 'Erika', 'Musterfrau', '1970-01-01')]);

        self::assertNull($matcher->match($this->application([$this->applicant('Erika', 'Musterfrau', '1990-06-15')]), $this->now()));
    }

    public function testIgnoresMembersWhoHaveLeft(): void
    {
        $matcher = $this->matcher([$this->member('1', 'Bad-1', 'Erika', 'Musterfrau', '1990-06-15', leftAt: '2025-12-31')]);

        self::assertNull($matcher->match($this->application([$this->applicant('Erika', 'Musterfrau', '1990-06-15')]), $this->now()));
    }

    /** Der Haushalt der ersten gefundenen Person ist das Ziel; Personen aus einem anderen Haushalt melden einen Konflikt. */
    public function testReportsPersonsFromAnotherHouseholdAsConflict(): void
    {
        $matcher = $this->matcher([
            $this->member('1', 'Bad-1', 'Erika', 'Musterfrau', '1990-06-15'),
            $this->member('2', 'Bad-2', 'Max', 'Mustermann', '1988-02-02'),
        ]);

        $match = $matcher->match($this->application([
            $this->applicant('Erika', 'Musterfrau', '1990-06-15'),
            $this->applicant('Max', 'Mustermann', '1988-02-02', position: 1),
            $this->applicant('Paul', 'Musterfrau', '2016-04-01', position: 2),
        ]), $this->now());

        self::assertNotNull($match);
        self::assertSame('Bad-1', $match->primaryMemberNumber);
        self::assertSame([0], array_keys($match->matchedMembers));
        self::assertSame(['Bad-2'], $match->conflictingMemberNumbers);
    }

    /** Die Bankverbindung gehört dem zahlenden Mitglied, nicht dem mitversicherten Familienangehörigen. */
    public function testAssignsTheBankAccountToThePayerOfTheFirstPerson(): void
    {
        $payer = $this->member('1', 'Bad-1', 'Erika', 'Musterfrau', '1990-06-15');
        $partner = $this->member('2', 'Bad-1', 'Max', 'Musterfrau', '1988-02-02', payerMemberId: '1');

        $match = $this->matcher([$payer, $partner])->match($this->application([$this->applicant('Max', 'Musterfrau', '1988-02-02')]), $this->now());

        self::assertNotNull($match);
        self::assertSame($payer, $match->bankAccountMember);
    }

    /** Aktive Haushaltsmitglieder ohne Treffer im Antrag werden gemeldet; Hauptmitglied und Zahler sind vor einem Austritt geschützt. */
    public function testReportsActiveHouseholdMembersMissingFromTheApplication(): void
    {
        $head = $this->member('1', 'Bad-1', 'Erika', 'Musterfrau', '1990-06-15');
        $partner = $this->member('2', 'Bad-1', 'Max', 'Musterfrau', '1988-02-02', payerMemberId: '1');
        $child = $this->member('3', 'Bad-1', 'Lena', 'Musterfrau', '2012-03-03', payerMemberId: '2');
        $formerChild = $this->member('4', 'Bad-1', 'Tom', 'Musterfrau', '2000-01-01', leftAt: '2024-12-31', payerMemberId: '1');

        $match = $this->matcher([$head, $partner, $child, $formerChild])->match($this->application([
            $this->applicant('Max', 'Musterfrau', '1988-02-02'),
            $this->applicant('Lena', 'Musterfrau', '2012-03-03', position: 1),
        ]), $this->now());

        self::assertNotNull($match);
        self::assertSame([$head], $match->missingMembers);
        self::assertSame('Hauptmitglied des Haushalts', $match->removalBlocker($head));
        self::assertSame('zahlt für weitere Mitglieder', $match->removalBlocker($partner));
        self::assertNull($match->removalBlocker($child));
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    /**
     * @param list<Member> $members
     */
    private function matcher(array $members): ApplicationMemberMatcher
    {
        $byId = [];
        foreach ($members as $member) {
            $byId[$member->id] = $member;
        }
        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('search')->willReturnCallback(static fn (?string $term): array => array_values(array_filter(
            $members,
            static fn (Member $member): bool => $term !== null && (stripos($member->firstName, $term) !== false || stripos($member->lastName, $term) !== false),
        )));
        $manager->method('findByEmail')->willReturn([]);
        $manager->method('findByPrimaryMemberNumber')->willReturnCallback(static fn (?string $number): array => array_values(array_filter(
            $members,
            static fn (Member $member): bool => $member->primaryMemberNumber === $number,
        )));
        $manager->method('get')->willReturnCallback(static fn (string $id): Member => $byId[$id]);
        $manager->method('findByMemberNumber')->willReturnCallback(static function (string $number) use ($members): ?Member {
            foreach ($members as $member) {
                if ($member->memberNumber === $number) {
                    return $member;
                }
            }

            return null;
        });

        return new ApplicationMemberMatcher($manager);
    }

    /**
     * @param list<Applicant> $applicants
     */
    private function application(array $applicants): MembershipApplication
    {
        $at = $this->now();

        return new MembershipApplication(
            id: 'application-1',
            membershipType: count($applicants) > 1 ? MembershipType::Family : MembershipType::Individual,
            applicants: $applicants,
            accountHolder: 'Erika Musterfrau',
            iban: 'DE89370400440532013000',
            bankName: null,
            signerName: 'Erika Musterfrau',
            emailConsent: false,
            declarationVersion: 'v1',
            version: 0,
            submittedAt: $at,
            updatedAt: $at,
        );
    }

    private function applicant(string $firstName, string $lastName, string $birthDate, int $position = 0): Applicant
    {
        return new Applicant(
            id: 'applicant-'.$position,
            position: $position,
            salutation: Salutation::Ms,
            firstName: $firstName,
            lastName: $lastName,
            birthDate: new \DateTimeImmutable($birthDate),
            street: 'Kirchanger',
            houseNumber: '14',
            postalCode: '14822',
            city: 'Borkheide',
            phone: null,
            email: $position === 0 ? 'erika@example.test' : null,
        );
    }

    private function member(string $id, string $primaryMemberNumber, string $firstName, string $lastName, string $birthDate, ?string $leftAt = null, ?string $payerMemberId = null): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-'.$id,
            primaryMemberNumber: $primaryMemberNumber,
            salutation: Salutation::Ms,
            lastName: $lastName,
            firstName: $firstName,
            birthDate: new \DateTimeImmutable($birthDate),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: $leftAt === null ? null : new \DateTimeImmutable($leftAt),
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $payerMemberId === null ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: $payerMemberId,
            nextBookingMonth: null,
            nextBookingYear: null,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
        );
    }
}
