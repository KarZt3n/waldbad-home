<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Service;

use App\Logic\Event\HelpRequest\Service\EventHelpRequestMemberMatcher;
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

final class EventHelpRequestMemberMatcherTest extends TestCase
{
    public function testMatchesOnNameAloneWhenUniqueRegardlessOfBirthDateOrEmail(): void
    {
        $member = $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$member]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        // Abweichendes Geburtsdatum und keine E-Mail stören nicht, solange der Name allein eindeutig ist.
        $match = $matcher->match('  ERIKA ', ' musterfrau  ', new \DateTimeImmutable('1999-09-09'), null);

        self::assertSame($member, $match);
    }

    public function testDoesNotMatchWhenNeitherNameNorBirthDateFitAnyCandidate(): void
    {
        $member = $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$member]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertNull($matcher->match('Erik', 'Musterfrau', new \DateTimeImmutable('2000-12-24'), null));
    }

    /**
     * Ein Tippfehler im Vornamen verhindert den Treffer nicht, solange Nachname + Geburtsdatum
     * zusammen eindeutig zu genau einem Mitglied passen.
     */
    public function testTypoInFirstNameAloneIsStillResolvedByLastNamePlusBirthDate(): void
    {
        $member = $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$member]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertSame($member, $matcher->match('Erik', 'Musterfrau', new \DateTimeImmutable('1990-01-01'), null));
    }

    public function testAmbiguousNameIsNarrowedDownByExactBirthDate(): void
    {
        $wanted = $this->member('m2', 'FAM-2', 'Erika', 'Musterfrau', null, '1991-02-02');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01'),
            $wanted,
        ]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertSame($wanted, $matcher->match('Erika', 'Musterfrau', new \DateTimeImmutable('1991-02-02'), null));
    }

    public function testAmbiguousNameStaysUnmatchedWhenBirthDateMatchesNeitherAndNoEmailGiven(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01'),
            $this->member('m2', 'FAM-2', 'Erika', 'Musterfrau', null, '1991-02-02'),
        ]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertNull($matcher->match('Erika', 'Musterfrau', new \DateTimeImmutable('2000-03-03'), null));
    }

    public function testTypoInLastNameIsResolvedByFirstNamePlusBirthDate(): void
    {
        $wanted = $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        // Kein Treffer mit vollständigem (fehlerhaften) Nachnamen -> Suche fällt auf Vorname zurück.
        $members->method('search')->willReturnCallback(
            fn (?string $term): array => $term === 'Erika' ? [$wanted] : [],
        );
        $matcher = new EventHelpRequestMemberMatcher($members);

        $match = $matcher->match('Erika', 'Musterfroh', new \DateTimeImmutable('1990-01-01'), null);

        self::assertSame($wanted, $match);
    }

    public function testTypoInFirstNameIsResolvedByLastNamePlusBirthDate(): void
    {
        $wanted = $this->member('m1', 'FAM-1', 'Erika', 'Musterfrau', null, '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturnCallback(
            fn (?string $term): array => $term === 'Musterfrau' ? [$wanted] : [],
        );
        $matcher = new EventHelpRequestMemberMatcher($members);

        $match = $matcher->match('Eryka', 'Musterfrau', new \DateTimeImmutable('1990-01-01'), null);

        self::assertSame($wanted, $match);
    }

    /**
     * Deckt das Beispiel aus der Anforderung ab: "Sally Kuck" meldet sich mit der E-Mail-Adresse
     * ihres Hauptmitglieds "Karsten Kuck" an (nicht ihrer eigenen) — trotzdem eindeutig zuordenbar,
     * weil die E-Mail gegen den ganzen Haushalt geprüft wird, nicht nur gegen die Kandidatin selbst.
     * Zwei gleichnamige "Sally Kuck" in unterschiedlichen Haushalten bleiben nach Namen und
     * Geburtsdatum (hier ein Tippfehler, passt zu keiner) mehrdeutig; erst die Haushalts-E-Mail löst
     * die Mehrdeutigkeit auf.
     */
    public function testAmbiguousNameWithNonMatchingBirthDateIsNarrowedDownByHouseholdEmail(): void
    {
        $wantedSally = $this->member('sally-1', 'FAM-1', 'Sally', 'Kuck', null, '1990-06-15');
        $otherSally = $this->member('sally-2', 'FAM-2', 'Sally', 'Kuck', null, '1985-03-20');
        $karsten = $this->member('karsten-1', 'FAM-1', 'Karsten', 'Kuck', 'sass.karsten@googlemail.com', '1988-11-11');

        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturnCallback(function (?string $term) use ($wantedSally, $otherSally, $karsten): array {
            if ($term === 'Kuck') {
                return [$wantedSally, $otherSally, $karsten];
            }
            if ($term === 'Sally') {
                return [$wantedSally, $otherSally];
            }

            return [];
        });
        $members->method('findByPrimaryMemberNumber')->willReturnCallback(
            fn (string $primaryMemberNumber): array => match ($primaryMemberNumber) {
                'FAM-1' => [$wantedSally, $karsten],
                'FAM-2' => [$otherSally],
                default => [],
            },
        );
        $matcher = new EventHelpRequestMemberMatcher($members);

        // Geburtsdatum passt zu keiner der beiden "Sally Kuck" (Tippfehler).
        $match = $matcher->match('Sally', 'Kuck', new \DateTimeImmutable('1999-09-09'), ' Sass.Karsten@Googlemail.com ');

        self::assertSame($wantedSally, $match);
    }

    public function testAmbiguousNameStaysUnmatchedWhenEmailBelongsToNoHousehold(): void
    {
        $sally1 = $this->member('sally-1', 'FAM-1', 'Sally', 'Kuck', null, '1990-06-15');
        $sally2 = $this->member('sally-2', 'FAM-2', 'Sally', 'Kuck', null, '1985-03-20');

        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$sally1, $sally2]);
        $members->method('findByPrimaryMemberNumber')->willReturnCallback(
            fn (string $primaryMemberNumber): array => match ($primaryMemberNumber) {
                'FAM-1' => [$sally1],
                'FAM-2' => [$sally2],
                default => [],
            },
        );
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertNull($matcher->match('Sally', 'Kuck', new \DateTimeImmutable('1999-09-09'), 'unbekannt@example.test'));
    }

    private function member(string $id, string $primaryMemberNumber, string $firstName, string $lastName, ?string $email, string $birthDate): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-'.$id,
            primaryMemberNumber: $primaryMemberNumber,
            salutation: Salutation::Mr,
            lastName: $lastName,
            firstName: $firstName,
            birthDate: new \DateTimeImmutable($birthDate),
            street: 'Musterweg 1',
            postalCode: '14547',
            city: 'Borkheide',
            email: $email,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 1,
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
