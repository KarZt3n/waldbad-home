<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\Query;

use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestContact;
use App\Logic\Rental\Sauna\Booking\Query\ListSaunaBookingsQuery;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;
use PHPUnit\Framework\TestCase;

final class ListSaunaBookingsQueryTest extends TestCase
{
    public function testEnrichesLinkedBookingsWithCurrentMemberContact(): void
    {
        $manager = $this->createStub(SaunaBookingManagerInterface::class);
        $manager->method('all')->willReturn([$this->booking('b1', 'member-1'), $this->booking('b2', null)]);
        $contact = new SaunaGuestContact('M-100', 'Kirchanger 14', '14822', 'Borkheide', 'erika@example.test');
        $directory = $this->createMock(SaunaGuestDirectoryInterface::class);
        $directory->expects(self::once())->method('contacts')->with(['member-1'])->willReturn(['member-1' => $contact]);

        $responses = (new ListSaunaBookingsQuery($manager, $directory))->execute();

        self::assertSame($contact, $responses[0]->memberContact);
        self::assertNull($responses[1]->memberContact);
    }

    private function booking(string $id, ?string $memberId): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable('2026-10-05'),
            startTime: '18:00',
            endTime: '20:00',
            personCount: 4,
            priceCents: 2000,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: null,
            message: '',
            status: SaunaBookingStatus::Open,
            memberId: $memberId,
            memberNumber: $memberId === null ? null : 'M-100',
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
