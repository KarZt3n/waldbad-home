<?php

namespace App\UI\Membership\Member\Cli;

use App\Logic\Membership\Member\UseCase\ExemptBoardMemberFamiliesUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prüft alle Mitglieder auf ihre Funktion: Gehört zu einem Haushalt (gleiche Hauptnummer)
 * mindestens ein Vorstandsmitglied, wird bei allen Mitgliedern dieses Haushalts das Häkchen
 * „Beitragspflichtig“ entfernt. Ohne --execute nur ein Prüflauf (keine Schreibzugriffe).
 */
#[AsCommand(name: 'app:membership:exempt-board-families', description: 'Entfernt „Beitragspflichtig“ für alle Haushalte mit einem Vorstandsmitglied.')]
final class ExemptBoardMemberFamiliesCommand extends Command
{
    public function __construct(private readonly ExemptBoardMemberFamiliesUseCase $useCase)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Nach erfolgreicher Prüfung tatsächlich speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $execute = $input->getOption('execute') === true;

        $result = $this->useCase->execute($execute);

        if ($result->updatedMemberNumbers !== []) {
            $io->listing($result->updatedMemberNumbers);
        }
        $io->success(sprintf(
            '%s: %d Haushalt(e) mit Vorstandsmitglied, %d Mitglied(er) %s.',
            $execute ? 'Übernahme abgeschlossen' : 'Prüflauf ohne Änderungen',
            $result->householdsAffected,
            count($result->updatedMemberNumbers),
            $execute ? 'aktualisiert' : 'betroffen',
        ));

        return Command::SUCCESS;
    }
}
