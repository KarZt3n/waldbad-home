<?php

namespace App\UI\Membership\Member\Cli;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\MemberRecalculationError;
use App\Logic\Membership\Member\UseCase\RecalculateAllMemberContributionsUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * „Beiträge neu berechnen“ ohne Oberfläche — für den geplanten Lauf zum Jahreswechsel
 * (`.github/workflows/recalculate-contributions-prod.yaml`). Ohne --year das laufende Jahr.
 * Endet mit Fehlercode, sobald ein Mitglied nicht berechnet werden konnte, damit der Lauf auffällt.
 */
#[AsCommand(name: 'app:membership:recalculate-contributions', description: 'Berechnet die Beiträge aller Mitglieder für das laufende (oder angegebene) Beitragsjahr neu.')]
final class RecalculateMemberContributionsCommand extends Command
{
    public function __construct(private readonly RecalculateAllMemberContributionsUseCase $useCase)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('year', null, InputOption::VALUE_REQUIRED, 'Beitragsjahr (laufendes oder folgendes Jahr)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $yearOption = $input->getOption('year');
        if ($yearOption !== null && (!is_string($yearOption) || preg_match('/^\d{4}$/', $yearOption) !== 1)) {
            $io->error('--year erwartet eine vierstellige Jahreszahl.');

            return Command::INVALID;
        }

        try {
            $result = $this->useCase->execute($yearOption === null ? null : (int) $yearOption);
        } catch (BusinessRuleViolationException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($result->errors !== []) {
            $io->listing(array_map(
                static fn (MemberRecalculationError $error): string => sprintf('%s: %s', $error->memberNumber, $error->message),
                $result->errors,
            ));
            $io->error(sprintf('%d Mitglied(er) neu berechnet, %d nicht berechenbar.', $result->updated, count($result->errors)));

            return Command::FAILURE;
        }
        $io->success(sprintf('%d Mitglied(er) neu berechnet.', $result->updated));

        return Command::SUCCESS;
    }
}
