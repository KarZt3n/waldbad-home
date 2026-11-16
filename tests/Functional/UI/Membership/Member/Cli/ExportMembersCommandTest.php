<?php

namespace App\Tests\Functional\UI\Membership\Member\Cli;

use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Query\ExportMembersQuery;
use App\UI\Membership\Member\Cli\ExportMembersCommand;
use App\UI\Membership\Member\Http\MemberExportFormatter;
use App\UI\Membership\Member\Http\MemberExportZipArchive;
use App\UI\Membership\Member\Http\MemberResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ExportMembersCommandTest extends TestCase
{
    public function testWritesImportableZipsReadableOnlyByOwner(): void
    {
        $directory = sys_get_temp_dir().'/members-export-'.bin2hex(random_bytes(4));
        mkdir($directory);
        try {
            $tester = $this->tester();

            self::assertSame(0, $tester->execute(['directory' => $directory]));

            $zipArchive = new MemberExportZipArchive();
            $json = $zipArchive->extract((string) file_get_contents($directory.'/mitglieder-json.zip'), null, ['json', 'csv', 'xml']);
            self::assertSame('json', $json['format']);
            self::assertSame(['members' => []], json_decode($json['content'], true, 512, JSON_THROW_ON_ERROR));
            $csv = $zipArchive->extract((string) file_get_contents($directory.'/mitglieder-csv.zip'), null, ['json', 'csv', 'xml']);
            self::assertSame('csv', $csv['format']);
            self::assertSame(0600, fileperms($directory.'/mitglieder-json.zip') & 0777);
            self::assertSame([], glob($directory.'/*.part'));
            self::assertStringContainsString('0 Mitglied(er) exportiert', $tester->getDisplay());
        } finally {
            array_map(unlink(...), glob($directory.'/*') ?: []);
            rmdir($directory);
        }
    }

    public function testFailsForMissingDirectory(): void
    {
        self::assertSame(1, $this->tester()->execute(['directory' => sys_get_temp_dir().'/does-not-exist-'.bin2hex(random_bytes(4))]));
    }

    private function tester(): CommandTester
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findAllSnapshots')->willReturn([]);

        return new CommandTester(new ExportMembersCommand(
            new ExportMembersQuery($members),
            new MemberResponseFactory($members),
            new MemberExportFormatter(),
            new MemberExportZipArchive(),
        ));
    }
}
