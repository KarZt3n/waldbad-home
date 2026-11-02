<?php

namespace App\UI\Media\PhotoAlbum\Cli;

use App\Logic\Media\PhotoAlbum\UseCase\WarmUpPhotoAlbumCacheUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bewusst ein eigener Befehl statt eines Symfony-Cache-Warmers: `cache:warmup` läuft beim Deploy
 * vor den Migrationen, der Fotoarchiv-Cache muss aber gegen das migrierte Schema aufgebaut werden.
 */
#[AsCommand(
    name: 'app:photo-albums:warm-cache',
    description: 'Baut den serverseitigen Cache des Fotoarchivs neu auf (nach Deploy oder direkten Datenbankänderungen).',
)]
final class WarmUpPhotoAlbumCacheCommand extends Command
{
    public function __construct(private readonly WarmUpPhotoAlbumCacheUseCase $useCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->useCase->execute();
        (new SymfonyStyle($input, $output))->success('Cache des Fotoarchivs aufgebaut.');

        return Command::SUCCESS;
    }
}
