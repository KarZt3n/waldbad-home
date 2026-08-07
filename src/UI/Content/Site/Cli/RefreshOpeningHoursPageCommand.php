<?php

namespace App\UI\Content\Site\Cli;

use App\Logic\Content\Site\UseCase\RefreshOpeningHoursPageUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:refresh-opening-hours', description: 'Aktualisiert und veröffentlicht die Seite zu Öffnungszeiten und Eintritt.')]
final class RefreshOpeningHoursPageCommand extends Command
{
    public function __construct(private readonly RefreshOpeningHoursPageUseCase $useCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $page = $this->useCase->execute();
        (new SymfonyStyle($input, $output))->success(sprintf('Die Seite „%s“ wurde aktualisiert und veröffentlicht.', $page->title));

        return Command::SUCCESS;
    }
}
