<?php

namespace App\UI\Event\Schedule\Cli;

use App\Logic\Event\Schedule\UseCase\ImportEventContentBlocksUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:event-schedule:import-from-content-blocks',
    description: 'Übernimmt bestehende "Veranstaltung"-Inhaltsblöcke aus Seiten in das eigenständige Modul „Veranstaltungen“.',
)]
final class ImportEventContentBlocksCommand extends Command
{
    public function __construct(private readonly ImportEventContentBlocksUseCase $useCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->useCase->execute();

        if ($result->imported !== []) {
            $io->section(sprintf('%d Veranstaltung(en) neu übernommen', count($result->imported)));
            $io->listing($result->imported);
        }
        if ($result->skipped !== []) {
            $io->section(sprintf('%d Veranstaltung(en) bereits vorhanden (übersprungen)', count($result->skipped)));
            $io->listing($result->skipped);
        }
        if ($result->imported === [] && $result->skipped === []) {
            $io->note('Es wurden keine "Veranstaltung"-Inhaltsblöcke gefunden.');
        }

        $io->success('Import abgeschlossen. Die ursprünglichen Inhaltsblöcke in den Seiten bleiben unverändert erhalten.');

        return Command::SUCCESS;
    }
}
