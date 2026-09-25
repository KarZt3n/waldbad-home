<?php

namespace App\UI\Media\PhotoAlbum\Cli;

use App\Logic\Media\PhotoAlbum\UseCase\ImportPhotoAlbumsUseCase;
use App\UI\Media\PhotoAlbum\Http\PhotoAlbumRequestMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:photo-albums:import',
    description: 'Importiert Foto-Einträge aus einer JSON-Datei (z. B. die von der Bestandswebsite übernommenen Einträge).',
)]
final class ImportPhotoAlbumsCommand extends Command
{
    public function __construct(
        private readonly ImportPhotoAlbumsUseCase $useCase,
        private readonly PhotoAlbumRequestMapper $requestMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Pfad zur JSON-Datei', 'docs/content-migration/photo-albums.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        if (!is_string($file) || !is_file($file)) {
            $io->error('Die JSON-Datei wurde nicht gefunden.');

            return Command::FAILURE;
        }
        $content = file_get_contents($file);
        $entries = is_string($content) ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($entries) || !array_is_list($entries)) {
            $io->error('Die JSON-Datei muss eine Liste von Foto-Einträgen enthalten.');

            return Command::FAILURE;
        }

        $requests = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                $io->error('Jeder Foto-Eintrag muss ein Objekt sein.');

                return Command::FAILURE;
            }
            $requests[] = $this->requestMapper->fromArray(null, $entry);
        }
        $result = $this->useCase->execute($requests);
        $io->success(sprintf('%d Foto-Einträge angelegt, %d bereits vorhanden (übersprungen).', $result->created, $result->skipped));

        return Command::SUCCESS;
    }
}
