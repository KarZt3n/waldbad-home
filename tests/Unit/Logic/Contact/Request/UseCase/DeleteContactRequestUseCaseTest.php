<?php

namespace App\Tests\Unit\Logic\Contact\Request\UseCase;

use App\Logic\Contact\Request\Exception\ContactRequestNotFoundException;
use App\Logic\Contact\Request\Manager\ContactRequestManagerInterface;
use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Contact\Request\Model\ContactRequestStatus;
use App\Logic\Contact\Request\UseCase\DeleteContactRequestUseCase;
use PHPUnit\Framework\TestCase;

final class DeleteContactRequestUseCaseTest extends TestCase
{
    public function testDeletesTheLoadedRequest(): void
    {
        $request = new ContactRequest(
            id: 'request-1',
            name: 'Erika Musterfrau',
            email: 'erika@example.test',
            subject: null,
            message: 'Wann beginnt die Saison?',
            status: ContactRequestStatus::Resolved,
            submittedAt: new \DateTimeImmutable('2025-06-01T10:00:00+02:00'),
            updatedAt: new \DateTimeImmutable('2025-06-02T10:00:00+02:00'),
        );
        $manager = $this->createMock(ContactRequestManagerInterface::class);
        $manager->expects(self::once())->method('get')->with('request-1')->willReturn($request);
        $manager->expects(self::once())->method('delete')->with($request);

        (new DeleteContactRequestUseCase($manager))->execute('request-1');
    }

    public function testUnknownRequestIsNotDeleted(): void
    {
        $manager = $this->createMock(ContactRequestManagerInterface::class);
        $manager->method('get')->willThrowException(new ContactRequestNotFoundException('unbekannt'));
        $manager->expects(self::never())->method('delete');

        $this->expectException(ContactRequestNotFoundException::class);
        (new DeleteContactRequestUseCase($manager))->execute('unbekannt');
    }
}
