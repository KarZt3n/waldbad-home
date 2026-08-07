<?php

namespace App\UI\Content\Site\Cli;

use App\Logic\Content\Site\UseCase\AppendMembershipInformationUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:append-membership-information', description: 'Ergänzt Einwilligungserklärung und Beitragsordnung unter dem Mitgliedsantrag.')]
final class AppendMembershipInformationCommand extends Command
{
    public function __construct(private readonly AppendMembershipInformationUseCase $useCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $page = $this->useCase->execute();
        (new SymfonyStyle($input, $output))->success(sprintf('Die Seite „%s“ enthält jetzt Einwilligungserklärung und Beitragsordnung.', $page->title));

        return Command::SUCCESS;
    }
}
