<?php

namespace App\UI\Membership\Member\Cli;

use App\Logic\Membership\Member\Dto\SageGsImportRequest;
use App\Logic\Membership\Member\UseCase\ImportSageGsMembersUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsCommand(name: 'sage-gs:importer', description: 'Prüft und importiert einen Sage GS Vereins-XML-Export.')]
final class SageGsImportCommand extends Command
{
    public function __construct(
        private readonly SageGsXmlParser $parser,
        private readonly SageGsRowMapper $mapper,
        private readonly ImportSageGsMembersUseCase $importer,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Pfad zur XML-Datei')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Nach erfolgreicher Gesamtprüfung atomar importieren')
            ->addOption('mapping', null, InputOption::VALUE_REQUIRED, 'JSON: Datensatzposition auf korrigierte Zielfelder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $path = $input->getArgument('path');
            if (!is_string($path)) {
                throw new BadRequestHttpException('XML-Pfad fehlt.');
            }
            $rows = $this->parser->parse($this->read($path));
            $mappingPath = $input->getOption('mapping');
            if ($mappingPath !== null && !is_string($mappingPath)) {
                throw new BadRequestHttpException('Mapping-Pfad ungültig.');
            }
            $mapping = $this->mapping($mappingPath);
            foreach (array_keys($mapping) as $position) {
                if ($position > count($rows)) {
                    throw new BadRequestHttpException('Mapping verweist auf eine nicht vorhandene Datensatzposition.');
                }
            }
            $requests = [];
            $failed = false;
            foreach ($rows as $index => $row) {
                try {
                    $requests[$index + 1] = $this->mapper->map($row, $mapping[$index + 1] ?? [], true);
                } catch (BadRequestHttpException $exception) {
                    $io->writeln(sprintf('Datensatz %d: %s', $index + 1, $exception->getMessage()));
                    $failed = true;
                }
            }
            $io->writeln(sprintf('%d Datensätze gelesen; %d syntaktisch gültig.', count($rows), count($requests)));
            if ($failed) {
                $io->error('Keine Änderungen. Zuerst die gemeldeten Zuordnungen korrigieren.');
                return Command::FAILURE;
            }
            $result = $this->importer->execute(new SageGsImportRequest($requests, $input->getOption('execute') === true));
            foreach ($result->errors as $error) {
                $io->writeln(sprintf('Datensatz %d: %s', $error->rowNumber, $error->message));
            }
            if ($result->errors !== []) {
                $io->error('Keine Änderungen. Fachliche Prüfung fehlgeschlagen.');
                return Command::FAILURE;
            }
            $io->success(sprintf('%s: %d neue Mitglieder, %d bestehende übersprungen.', $input->getOption('execute') === true ? 'Import abgeschlossen' : 'Prüflauf ohne Änderungen', $result->created, $result->existing));
            return Command::SUCCESS;
        } catch (BadRequestHttpException $exception) {
            $io->error($exception->getMessage());
            return Command::INVALID;
        } catch (\Throwable) {
            $io->error('Import fehlgeschlagen; keine Änderungen übernommen. Datenbankverfügbarkeit und Schema prüfen.');
            return Command::FAILURE;
        }
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

    /** @return array<int, array<string, scalar|null>> */
    private function mapping(?string $path): array
    {
        if ($path === null) {
            return [];
        }
        try {
            $decoded = json_decode($this->read($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Mapping enthält kein gültiges JSON.');
        }
        if (!is_array($decoded)) {
            throw new BadRequestHttpException('Mapping muss ein Objekt sein.');
        }
        $result = [];
        foreach ($decoded as $position => $fields) {
            if (!is_int($position) || $position < 1 || !is_array($fields)) {
                throw new BadRequestHttpException('Mapping benötigt positive Datensatzpositionen mit Feldobjekten.');
            }
            $values = [];
            foreach ($fields as $key => $value) {
                if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                    throw new BadRequestHttpException('Mapping-Felder müssen skalare Werte enthalten.');
                }
                $values[$key] = $value;
            }
            $result[$position] = $values;
        }
        return $result;
    }
}
