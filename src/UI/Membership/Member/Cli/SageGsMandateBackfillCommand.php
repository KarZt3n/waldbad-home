<?php

namespace App\UI\Membership\Member\Cli;

use App\Logic\Membership\Member\Dto\MandateBackfillRow;
use App\Logic\Membership\Member\UseCase\BackfillSageGsMandateUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Ergänzt bei bereits importierten Mitgliedern (Zuordnung über MITNUM/member_number) die
 * Mandatsreferenz sowie Mandatsgültigkeit von/bis (MANDATSREFERENZ/MANDATABDATUM/MANDATBISDATUM)
 * aus einem Sage-GS-Export — für den Fall, dass diese beim ursprünglichen Bestandsimport noch
 * nicht übernommen wurden. Legt keine neuen Mitglieder an; unbekannte Mitgliedsnummern werden nur
 * gezählt.
 */
#[AsCommand(name: 'sage-gs:backfill-mandate', description: 'Ergänzt Mandatsreferenz/-gültigkeit bestehender Mitglieder aus einem Sage-GS-Export.')]
final class SageGsMandateBackfillCommand extends Command
{
    public function __construct(
        private readonly SageGsXmlParser $parser,
        private readonly BackfillSageGsMandateUseCase $backfill,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Pfad zur XML-Datei')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Nach erfolgreicher Prüfung atomar schreiben');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $path = $input->getArgument('path');
            if (!is_string($path)) {
                throw new BadRequestHttpException('XML-Pfad fehlt.');
            }
            $rawRows = $this->parser->parse($this->read($path));
            $rows = [];
            $failed = false;
            foreach ($rawRows as $index => $row) {
                try {
                    $rows[] = $this->row($row);
                } catch (BadRequestHttpException $exception) {
                    $io->writeln(sprintf('Datensatz %d: %s', $index + 1, $exception->getMessage()));
                    $failed = true;
                }
            }
            if ($failed) {
                $io->error('Keine Änderungen. Zuerst die gemeldeten Datumsfehler korrigieren.');
                return Command::FAILURE;
            }
            $execute = $input->getOption('execute') === true;
            $result = $this->backfill->execute($rows, $execute);
            $io->success(sprintf(
                '%s: %d Mitglieder aktualisiert, %d unverändert, %d Mitgliedsnummern nicht gefunden.',
                $execute ? 'Übernahme abgeschlossen' : 'Prüflauf ohne Änderungen',
                $result->updated,
                $result->unchanged,
                $result->notFound,
            ));
            return Command::SUCCESS;
        } catch (BadRequestHttpException $exception) {
            $io->error($exception->getMessage());
            return Command::INVALID;
        } catch (\Throwable) {
            $io->error('Übernahme fehlgeschlagen; keine Änderungen übernommen. Datenbankverfügbarkeit und Schema prüfen.');
            return Command::FAILURE;
        }
    }

    /**
     * @param array<string, string> $row
     */
    private function row(array $row): MandateBackfillRow
    {
        $memberNumber = trim($row['MITNUM'] ?? '');
        if ($memberNumber === '') {
            throw new BadRequestHttpException('Fehlende Mitgliedsnummer (MITNUM).');
        }

        return new MandateBackfillRow(
            memberNumber: $memberNumber,
            mandateReference: ($row['MANDATSREFERENZ'] ?? '') === '' ? null : $row['MANDATSREFERENZ'],
            mandateValidFrom: $this->date($row['MANDATABDATUM'] ?? '', 'MANDATABDATUM'),
            mandateValidUntil: $this->date($row['MANDATBISDATUM'] ?? '', 'MANDATBISDATUM'),
        );
    }

    private function date(string $value, string $field): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $value);
        if ($date === false || $date->format('d.m.Y') !== $value) {
            throw new BadRequestHttpException('Ungültiges Datumsformat in '.$field.'.');
        }

        return $date;
    }

    private function read(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new BadRequestHttpException('Importdatei nicht lesbar.');
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new BadRequestHttpException('Importdatei nicht lesbar.');
        }
        return $content;
    }
}
