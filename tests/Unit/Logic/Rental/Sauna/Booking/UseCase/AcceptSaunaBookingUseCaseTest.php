<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestContact;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaBookingAcceptance;
use App\Logic\Rental\Sauna\Booking\Service\SaunaRequesterEmailResolver;
use App\Logic\Rental\Sauna\Booking\UseCase\AcceptSaunaBookingUseCase;
use PHPUnit\Framework\TestCase;

final class AcceptSaunaBookingUseCaseTest extends TestCase
{
    public function testAcceptsWhenNoOtherAcceptedBookingOverlaps(): void
    {
        $booking = $this->booking('b1', SaunaBookingStatus::Open);
        $competingOpenRequest = $this->booking('b2', SaunaBookingStatus::Open);
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($booking);
        $manager->method('findBetween')->willReturn([$booking, $competingOpenRequest]);
        $manager->expects(self::once())->method('saveAll')
            ->with(self::callback(static fn (array $saved): bool => $saved[0] instanceof SaunaBooking && $saved[0]->status === SaunaBookingStatus::Accepted))
            ->willReturnArgument(0);

        $response = $this->useCase($manager)->execute('b1');

        self::assertSame(SaunaBookingStatus::Accepted, $response->status);
    }

    public function testRefusesWhenAnotherAcceptedBookingOverlaps(): void
    {
        $booking = $this->booking('b1', SaunaBookingStatus::Open);
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($booking);
        $manager->method('findBetween')->willReturn([$booking, $this->booking('b2', SaunaBookingStatus::Accepted)]);
        $manager->expects(self::never())->method('saveAll');
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingsAccepted');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($manager, $notifier)->execute('b1');
    }

    public function testConfirmsToTheEmailGivenInTheRequest(): void
    {
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::once())->method('bookingsAccepted')->with(
            self::callback(static fn (array $bookings): bool => count($bookings) === 1
                && $bookings[0] instanceof SaunaBooking && $bookings[0]->status === SaunaBookingStatus::Accepted),
            'erika@example.test',
        );

        $booking = $this->booking('b1', SaunaBookingStatus::Open, email: 'erika@example.test', memberId: 'member-7');
        $this->useCase($this->savingManager($booking), $notifier, 'member@example.test')->execute('b1');
    }

    public function testConfirmsToTheLinkedMemberWithoutEmailInTheRequest(): void
    {
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::once())->method('bookingsAccepted')->with(self::anything(), 'member@example.test');

        $booking = $this->booking('b1', SaunaBookingStatus::Open, memberId: 'member-7');
        $this->useCase($this->savingManager($booking), $notifier, 'member@example.test')->execute('b1');
    }

    public function testSendsNothingWithoutAnyEmailAddress(): void
    {
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingsAccepted');

        $booking = $this->booking('b1', SaunaBookingStatus::Open, memberId: 'member-7');
        $this->useCase($this->savingManager($booking), $notifier, null)->execute('b1');
    }

    private function useCase(
        SaunaBookingManagerInterface $manager,
        ?SaunaBookingNotifierInterface $notifier = null,
        ?string $memberEmail = null,
    ): AcceptSaunaBookingUseCase {
        $directory = $this->createStub(SaunaGuestDirectoryInterface::class);
        $directory->method('contacts')->willReturn([
            'member-7' => new SaunaGuestContact('M-7', 'Waldweg 1', '14822', 'Borkheide', $memberEmail),
        ]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-26T09:00:00'));

        return new AcceptSaunaBookingUseCase($manager, new SaunaBookingAcceptance(
            $manager,
            new SaunaRequesterEmailResolver($directory),
            $notifier ?? $this->createStub(SaunaBookingNotifierInterface::class),
            $clock,
        ));
    }

    private function savingManager(SaunaBooking $booking): SaunaBookingManagerInterface
    {
        $manager = $this->createStub(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($booking);
        $manager->method('findBetween')->willReturn([$booking]);
        $manager->method('saveAll')->willReturnArgument(0);

        return $manager;
    }

    private function booking(string $id, SaunaBookingStatus $status, ?string $email = null, ?string $memberId = null): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable('2026-10-05'),
            startTime: '18:00',
            endTime: '19:00',
            personCount: 4,
            priceCents: 2000,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: $email,
            message: '',
            status: $status,
            memberId: $memberId,
            memberNumber: $memberId === null ? null : 'M-7',
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
