<?php

namespace App\UI\Membership\Member\Cli;

use App\Logic\Membership\Member\Query\ExportMembersQuery;
use App\UI\Membership\Member\Http\MemberExportFormatter;
use App\UI\Membership\Member\Http\MemberExportZipArchive;
use App\UI\Membership\Member\Http\MemberResponseFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Schreibt den vollständigen Mitglieder-Export (wie „Mitglieder exportieren“ im Admin, inkl. gelöschter
 * Mitglieder, IDs und Zeitstempel) als mitglieder-json.zip und mitglieder-csv.zip in ein Verzeichnis —
 * dasselbe unverschlüsselte ZIP-Format wie der Admin-Export. Für die nächtliche Sicherung: Jede der
 * Dateien lässt sich im Admin über „Mitglieder importieren“ 1:1 wiederherstellen, ohne Zugriff auf die
 * Datenbank.
 */
#[AsCommand(name: 'app:membership:export-members', description: 'Schreibt den vollständigen Mitglieder-Export (JSON und CSV als ZIP) in ein Verzeichnis.')]
final class ExportMembersCommand extends Command
{
    public function __construct(
        private readonly ExportMembersQuery $query,
        private readonly MemberResponseFactory $responseFactory,
        private readonly MemberExportFormatter $formatter,
        private readonly MemberExportZipArchive $zipArchive,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Zielverzeichnis (muss existieren)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        if (!is_string($directory) || !is_dir($directory) || !is_writable($directory)) {
            $io->error('Das Zielverzeichnis existiert nicht oder ist nicht beschreibbar.');

            return Command::FAILURE;
        }

        $directory = rtrim($directory, '/');
        $rows = array_map($this->responseFactory->exportRow(...), $this->query->execute());
        foreach (['json' => $this->formatter->toJson($rows), 'csv' => $this->formatter->toCsv($rows)] as $format => $content) {
            $path = sprintf('%s/mitglieder-%s.zip', $directory, $format);
            $content = $this->zipArchive->build($content, $format, null);
            // Erst vollständig schreiben, dann umbenennen: Ein abgebrochener Lauf hinterlässt keine halbe Datei.
            if (file_put_contents($path.'.part', $content) === false || !chmod($path.'.part', 0600) || !rename($path.'.part', $path)) {
                $io->error(sprintf('mitglieder-%s.zip konnte nicht geschrieben werden.', $format));

                return Command::FAILURE;
            }
        }
        $io->success(sprintf('%d Mitglied(er) exportiert.', count($rows)));

        return Command::SUCCESS;
    }
}
