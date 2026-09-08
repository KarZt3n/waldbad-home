<?php

namespace App\Tests\Functional\UI\Membership\Member\Cli;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\MemberImportTransactionInterface;
use App\Logic\Membership\Member\UseCase\ImportSageGsMembersUseCase;
use App\UI\Membership\Member\Cli\{SageGsImportCommand, SageGsRowMapper, SageGsXmlParser};
use App\UI\Membership\Member\Http\MemberRequestMapper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SageGsImportCommandTest extends WebTestCase
{
    public function testDatapacketReadsAttributesAndRejectsDtd(): void
    {
        $parser = new SageGsXmlParser();
        self::assertSame([['MITNUM' => 'TEST-1']], $parser->parse('<DATAPACKET><ROWDATA><ROW MITNUM="TEST-1"/></ROWDATA></DATAPACKET>'));
        $this->expectException(BadRequestHttpException::class);
        $parser->parse('<!DOCTYPE DATAPACKET [<!ENTITY test "example">]><DATAPACKET><ROWDATA><ROW/></ROWDATA></DATAPACKET>');
    }

    public function testSageFieldMappingAndConfirmedFamilyPayerFallback(): void
    {
        $request = (new SageGsRowMapper(new MemberRequestMapper()))->map([
            'MITNUM' => 'TEST-2', 'FANUM' => 'TEST-1', 'ANREDE' => 'Frau',
            'NAME' => 'Beispiel', 'VORNAME' => 'Test', 'GEBURT' => '02.03.2000',
            'MITSEIT' => '01.01.2020', 'AUSTRITT' => '31.12.2025',
            'STRASSE' => 'Testweg 1', 'PLZ' => '01234', 'ORT' => 'Testort',
            'AKTIV' => 'Wahr', 'ZAHLANFANG' => 'Falsch', 'ZAHLFREMD' => 'Wahr',
            'ZAHLART' => 'Bankeinzug', 'ZAHLWEISE' => 'jährlich', 'NM' => '03', 'NJ' => '27',
        ], ['familyRole' => 'partner'], true);
        self::assertSame('TEST-1', $request->payerMemberNumber);
        self::assertSame('01234', $request->postalCode);
        self::assertSame('2000-03-02', $request->birthDate->format('Y-m-d'));
        self::assertFalse($request->active);
        self::assertSame(2027, $request->nextBookingYear);
        self::assertSame('fifteenth', $request->paymentDay->value);
    }

    public function testInvalidInputDoesNotAccessDatabaseOrExposeValues(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->expects(self::never())->method('findByMemberNumber');
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::never())->method('execute');
        $command = new SageGsImportCommand(new SageGsXmlParser(), new SageGsRowMapper(new MemberRequestMapper()),
            new ImportSageGsMembersUseCase($members, $this->createStub(IdentifierGeneratorInterface::class), new MemberModelFactory(), $transaction));
        $path = tempnam(sys_get_temp_dir(), 'sage-test-');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, '<DATAPACKET><ROWDATA><ROW MITNUM="SENSITIVE-TEST-MARKER" AKTIV="Wahr" ZAHLANFANG="Wahr" ZAHLFREMD="Falsch"/></ROWDATA></DATAPACKET>');
            $tester = new CommandTester($command);
            self::assertSame(1, $tester->execute(['path' => $path, '--execute' => true]));
            self::assertStringNotContainsString('SENSITIVE-TEST-MARKER', $tester->getDisplay());
            self::assertStringContainsString('Datensatz 1', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }
}
