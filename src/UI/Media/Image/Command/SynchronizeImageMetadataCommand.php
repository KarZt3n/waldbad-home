<?php

namespace App\UI\Media\Image\Command;

use App\Logic\Media\Image\UseCase\SynchronizeImageMetadataUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:media:synchronize-metadata',
    description: 'Übernimmt Metadaten vorhandener Mediendateien in die Datenbank.',
)]
final class SynchronizeImageMetadataCommand extends Command
{
    public function __construct(private readonly SynchronizeImageMetadataUseCase $useCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->useCase->execute();
        $output->writeln(sprintf('%d Mediendateien wurden mit der Datenbank abgeglichen.', $count));

        return Command::SUCCESS;
    }
}
