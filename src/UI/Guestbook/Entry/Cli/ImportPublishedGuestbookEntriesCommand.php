<?php

namespace App\UI\Guestbook\Entry\Cli;

use App\Logic\Guestbook\Entry\UseCase\ImportPublishedGuestbookEntryUseCase;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:guestbook:import-published', description: 'Importiert bereits veröffentlichte Gästebucheinträge aus einer JSON-Datei.')]
class ImportPublishedGuestbookEntriesCommand extends Command
{
    public function __construct(private readonly ImportPublishedGuestbookEntryUseCase $useCase)
    {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Pfad zur JSON-Datei mit displayName, date, time und message.')] string $file,
    ): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $entries = $this->readEntries($file);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $imported = 0;
        foreach ($entries as $index => $entry) {
            try {
                $submittedAt = new \DateTimeImmutable(
                    sprintf('%s %s', $entry['date'], $entry['time']),
                    new \DateTimeZone('Europe/Berlin'),
                );
            } catch (\Exception $exception) {
                $io->error(sprintf('Eintrag %d enthält ein ungültiges Datum oder eine ungültige Uhrzeit: %s', $index + 1, $exception->getMessage()));

                return Command::FAILURE;
            }

            if ($this->useCase->execute($entry['displayName'], $entry['message'], $submittedAt)) {
                ++$imported;
            }
        }

        $io->success(sprintf('%d Einträge importiert, %d bereits vorhanden.', $imported, count($entries) - $imported));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{displayName: string, date: string, time: string, message: string}>
     */
    private function readEntries(string $file): array
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Die Datei "%s" kann nicht gelesen werden.', $file));
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Die JSON-Datei ist ungültig: '.$exception->getMessage(), previous: $exception);
        }

        if (!is_array($data) || !array_is_list($data)) {
            throw new \RuntimeException('Die JSON-Datei muss eine Liste von Einträgen enthalten.');
        }

        $entries = [];
        foreach ($data as $index => $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException(sprintf('Eintrag %d ist kein Objekt.', $index + 1));
            }

            $entries[] = [
                'displayName' => $this->requiredString($entry, 'displayName', $index),
                'date' => $this->requiredString($entry, 'date', $index),
                'time' => $this->requiredString($entry, 'time', $index),
                'message' => $this->requiredString($entry, 'message', $index),
            ];
        }

        return $entries;
    }

    /**
     * @param array<mixed> $entry
     *
     * @return non-empty-string
     */
    private function requiredString(array $entry, string $field, int $index): string
    {
        $value = $entry[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(sprintf('Eintrag %d enthält kein gültiges Feld "%s".', $index + 1, $field));
        }

        return $value;
    }
}
