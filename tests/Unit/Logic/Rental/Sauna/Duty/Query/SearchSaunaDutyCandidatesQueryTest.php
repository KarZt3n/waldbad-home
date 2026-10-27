<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Duty\Query;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;
use App\Logic\Rental\Sauna\Duty\Query\SearchSaunaDutyCandidatesQuery;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;
use PHPUnit\Framework\TestCase;

final class SearchSaunaDutyCandidatesQueryTest extends TestCase
{
    public function testBoardMembersComeFirstAlphabeticallyEvenBeyondTheLimit(): void
    {
        // Mehr alphabetisch frühere Treffer als das Limit: Die Vorstandsmitglieder am Ende des
        // Alphabets dürfen trotzdem nicht herausfallen.
        $candidates = [];
        for ($i = 0; $i < SearchSaunaDutyCandidatesQuery::LIMIT + 5; ++$i) {
            $candidates[] = $this->candidate(sprintf('Abel%02d', $i), false);
        }
        $candidates[] = $this->candidate('Wagner', true);
        $candidates[] = $this->candidate('Zander', true);

        $directory = $this->createStub(SaunaDutyMemberDirectoryInterface::class);
        $directory->method('search')->willReturn($candidates);

        $result = (new SearchSaunaDutyCandidatesQuery($directory))->execute('a');

        self::assertCount(SearchSaunaDutyCandidatesQuery::LIMIT, $result);
        self::assertSame(
            ['Wagner', 'Zander', 'Abel00', 'Abel01'],
            array_map(static fn (SaunaDutyCandidate $candidate): string => $candidate->lastName, array_slice($result, 0, 4)),
        );
    }

    private function candidate(string $lastName, bool $boardMember): SaunaDutyCandidate
    {
        return new SaunaDutyCandidate(
            memberId: 'id-'.$lastName,
            memberNumber: 'Bad-'.$lastName,
            firstName: 'Max',
            lastName: $lastName,
            street: 'Kirchanger 14',
            birthDate: null,
            boardMember: $boardMember,
        );
    }
}
