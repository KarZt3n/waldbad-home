<?php

namespace App\UI\Content\Site\Cli;

use App\Logic\Content\Site\UseCase\InitializeSiteUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:initialize', description: 'Legt die initialen Waldbad-Seiten an.')]
class InitializeSiteCommand extends Command
{
    public function __construct(private readonly InitializeSiteUseCase $useCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = $this->useCase->execute();
        $io->success(sprintf('%d Seiten wurden angelegt.', $created));

        return Command::SUCCESS;
    }
}
